<?php

namespace Drupal\migrate_wbm\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

class MigrateWbmCommands extends DrushCommands {

  protected $entityTypeManager;
  protected $configFactory;
  protected $logger;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * Migrate Workbench Moderation & Email to Content Moderation.
   *
   * @command migrate_wbm:run
   * @aliases mwbm
   */
  public function migrate() {
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
      'create_new_draft' => [
        'label' => 'Create Draft',
        'from' => ['published'],
        'to' => 'draft',
      ],
      'submit_for_review' => [
        'label' => 'Submit for Review',
        'from' => ['draft'],
        'to' => 'needs_review',
      ],
      'publish' => [
        'label' => 'Publish',
        'from' => ['draft', 'needs_review'],
        'to' => 'published',
      ],
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

}
