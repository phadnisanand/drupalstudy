<?php

namespace Drupal\mymodule\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\node\Entity\Node;

/**
 * Custom Drush commands.
 */
class MyModuleCommands extends DrushCommands {

  /**
   * Update moderation state for nodes from a CSV file.
   *
   * @command mymodule:update-from-csv
   * @aliases mufc
   * @usage drush mymodule:update-from-csv /path/to/nodes.csv
   *   Reads a CSV file and updates moderation states.
   *
   * @param string $filepath
   *   The CSV file path.
   */
   // drush mymodule:update-from-csv /full/path/to/nodes.csv
  public function updateFromCsv($filepath) {
    if (!file_exists($filepath)) {
      $this->logger()->error("CSV file not found: $filepath");
      return;
    }

    if (($handle = fopen($filepath, 'r')) !== FALSE) {
      $header = fgetcsv($handle); // read header row
      while (($row = fgetcsv($handle)) !== FALSE) {
        $data = array_combine($header, $row);
        $nid = $data['nid'] ?? NULL;
        $state = $data['state'] ?? NULL;

        if ($nid && $state) {
          $node = Node::load($nid);
          if ($node && $node->hasField('moderation_state')) {
            $node->set('moderation_state', $state);
            $node->save();
            $this->logger()->success("Node $nid updated to state '$state'.");
          }
          else {
            $this->logger()->warning("Node $nid not found or has no moderation state.");
          }
        }
      }
      fclose($handle);
    }
  }

}
