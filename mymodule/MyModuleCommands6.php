<?php

namespace Drupal\mymodule\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom Drush commands for updating moderation states.
 */
class MyModuleCommands extends DrushCommands {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct();
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Update moderation states from CSV file.
   *
   * @command mymodule:update-moderation
   * @aliases mum
   * @usage drush mymodule:update-moderation /path/to/file.csv
   *   Reads CSV and updates moderation states.
   *
   * @param string $filepath
   *   Path to CSV file.
   */
  public function updateModeration($filepath) {
    if (!file_exists($filepath)) {
      $this->logger()->error("CSV file not found: $filepath");
      return;
    }

    if (($handle = fopen($filepath, 'r')) !== FALSE) {
      $header = fgetcsv($handle); // read header
      while (($row = fgetcsv($handle)) !== FALSE) {
        $data = array_combine($header, $row);
        $entity_type = $data['entity_type'] ?? NULL;
        $id = $data['id'] ?? NULL;
        $state = $data['state'] ?? NULL;

        if ($entity_type && $id && $state) {
          $storage = $this->entityTypeManager->getStorage($entity_type);
          $entity = $storage->load($id);

          if ($entity && $entity->hasField('moderation_state')) {
            $entity->set('moderation_state', $state);
            $entity->save();
            $this->logger()->success(ucfirst($entity_type) . " $id moderation state changed to '$state'.");
          }
          else {
            $this->logger()->warning("Entity $entity_type:$id not found or does not support moderation state.");
          }
        }
      }
      fclose($handle);
    }
  }

}
