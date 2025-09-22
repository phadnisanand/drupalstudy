<?php

namespace Drupal\wbm_to_cm_migrator\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class WbmToCmMigratorCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a WbmToCmMigratorCommands object.
   */
  public function __construct(
    private readonly Token $token,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct();
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'wbm_to_cm_migrator:command-name', aliases: ['foo'])]
  #[CLI\Argument(name: 'arg1', description: 'Argument description.')]
  #[CLI\Option(name: 'option-name', description: 'Option description')]
  #[CLI\Usage(name: 'wbm_to_cm_migrator:command-name foo', description: 'Usage description')]
  public function commandName($arg1, $options = ['option-name' => 'default']) {
     $this->output()->writeln("🚀 Starting Workbench Moderation migration...");

    $workflow_storage = $this->entityTypeManager->getStorage('workflow');
    $state_storage = $this->entityTypeManager->getStorage('moderation_state');

    // 1. Create or load workflow.
    $workflow = $workflow_storage->load('content_moderation');
    if (!$workflow) {
      $workflow = $workflow_storage->create([
        'id' => 'content_moderation',
        'label' => 'Content Moderation',
        'type' => 'content_moderation',
      ]);
      $workflow->save();
      $this->output()->writeln("✔ Workflow created.");
    }

    // 2. Add moderation states.
    $states = [
      'archived' => 'Archived',
	  'draft' => 'Draft',
      'needs_review' => 'Needs Review',
      'published' => 'Published',
    ];

    foreach ($states as $id => $label) {
      if (!$state_storage->load($id)) {
        $state = $state_storage->create([
          'id' => $id,
          'label' => $label,
          'published' => ($id === 'published'),
          'default_revision' => ($id === 'published'),
          'workflow' => 'content_moderation',
        ]);
        $state->save();
        $this->output()->writeln("✔ State added: $id");
      }
    }

    // 3. Add transitions.
    $transitions = [
      /*'draft_draft' => [
        'label' => 'Create New Draft',
        'from' => ['draft'],
        'to' => 'draft',
      ],*/
      /*'draft_needs_review' => [
        'label' => 'Request Review',
        'from' => ['draft'],
        'to' => 'needs_review',
      ],*/
      /*'draft_published' => [
        'label' => 'Publish',
        'from' => ['draft'],
        'to' => 'published',
      ],*/
	 /* 'needs_review_needs_review' => [
        'label' => 'Keep in Review',
        'from' => ['needs_review'],
        'to' => 'needs_review',
      ],*/
	  /* 'needs_review_published' => [
        'label' => 'Publish',
        'from' => ['needs_review'],
        'to' => 'published',
      ],*/
	  /* 'needs_review_draft' => [
        'label' => 'Send Back to Draft',
        'from' => ['needs_review'],
        'to' => 'draft',
      ],*/
	   /*'published_draft' => [
        'label' => 'Create New Draft',
        'from' => ['published'],
        'to' => 'draft',
      ],*/
	  /* 'published_published' => [
        'label' => 'Publish',
        'from' => ['published'],
        'to' => 'published',
      ],*/
	   /*'published_archived' => [
        'label' => 'Archive',
        'from' => ['published'],
        'to' => 'archived',
      ],*/
	 /*  'archived_published' => [
        'label' => 'Un-archive',
        'from' => ['archived'],
        'to' => 'published',
      ],*/
    ];

    foreach ($transitions as $id => $data) {
      // Use workflow plugin API to check/add transitions.
      $plugin = $workflow->getTypePlugin();
      if (empty($plugin->getTransition($id))) {
        $plugin->addTransition($id, $data['label'], $data['from'], $data['to']);
        $this->output()->writeln("✔ Transition added: $id");
      }
    }
    $workflow->save();

    // 4. Attach workflow to all node types.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $type) {
      $workflow->getTypePlugin()->addEntityTypeAndBundle('node', $type->id());
      $this->output()->writeln("✔ Workflow applied to: {$type->id()}");
    }
    $workflow->save();

    // 5. Migrate Workbench Email → Content Moderation Notifications.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation_notifications')) {
      $config = $this->configFactory->get('workbench_email.settings');
      $mappings = $config->get('transitions') ?? [];

      foreach ($mappings as $transition_id => $settings) {
        try {
          $notification = \Drupal\content_moderation_notifications\Entity\ContentModerationNotification::create([
            'id' => 'migrated_' . $transition_id,
            'label' => 'Migrated from Workbench: ' . $transition_id,
            'workflow' => 'content_moderation',
            'transition' => $transition_id,
            'status' => TRUE,
            'recipients' => [
              'roles' => $settings['roles'] ?? [],
              'emails' => $settings['emails'] ?? [],
            ],
            'subject' => $settings['subject'] ?? 'Content moderation update',
            'body' => $settings['body'] ?? 'Content has changed moderation state.',
          ]);
          $notification->save();
          $this->output()->writeln("✔ Migrated email for: $transition_id");
        }
        catch (\Exception $e) {
          $this->logger->error("Failed migrating Workbench Email rule for $transition_id: " . $e->getMessage());
        }
      }
    }
    else {
      $this->output()->writeln("⚠ Content Moderation Notifications module not enabled — skipping email migration.");
    }

    $this->output()->writeln("🎉 Migration complete!");
  }

  /**
   * An example of the table output format.
   */
  #[CLI\Command(name: 'wbm_to_cm_migrator:token', aliases: ['token'])]
  #[CLI\FieldLabels(labels: [
    'group' => 'Group',
    'token' => 'Token',
    'name' => 'Name'
  ])]
  #[CLI\DefaultTableFields(fields: ['group', 'token', 'name'])]
  #[CLI\FilterDefaultField(field: 'name')]
  public function token($options = ['format' => 'table']): RowsOfFields {
    $all = $this->token->getInfo();
    foreach ($all['tokens'] as $group => $tokens) {
      foreach ($tokens as $key => $token) {
        $rows[] = [
          'group' => $group,
          'token' => $key,
          'name' => $token['name'],
        ];
      }
    }
    return new RowsOfFields($rows);
  }

}
