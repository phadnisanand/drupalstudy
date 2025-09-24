<?php

namespace Drupal\mymodule\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;

/**
 * Custom Drush command for moderation state updates.
 */ 
//drush mymodule:update-moderation /full/path/to/moderation.csv

class MyModuleCommands extends DrushCommands {

  /**
   * Update node moderation state from CSV file.
   *
   * @command mymodule:update-moderation
   * @aliases mum
   * @usage drush mymodule:update-moderation /path/to/file.csv
   *   Reads a CSV and updates node moderation states.
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
        $nid = $data['nid'] ?? NULL;
        $state = $data['state'] ?? NULL;

        if ($nid && $state) {
          $node = Node::load($nid);
          if ($node && $node->hasField('moderation_state')) {
            $node->set('moderation_state', $state);
            $node->save();
            $this->logger()->success("Node $nid moderation state changed to '$state'.");
          }
          else {
            $this->logger()->warning("Node $nid not found or has no moderation state field.");
          }
        }
      }
      fclose($handle);
    }
  }

}
