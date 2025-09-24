<?php

namespace Drupal\mymodule\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\block\Entity\Block;

/**
 * Custom Drush commands to update block state from CSV.
 */
class MyModuleCommands extends DrushCommands {

  /**
   * Enable/disable blocks based on CSV file.
   *
   * @command mymodule:update-blocks
   * @aliases mub
   * @usage drush mymodule:update-blocks /path/to/blocks.csv
   *   Reads a CSV and updates block states.
   *
   * @param string $filepath
   *   Path to the CSV file.
   */
  public function updateBlocks($filepath) {
    if (!file_exists($filepath)) {
      $this->logger()->error("CSV file not found: $filepath");
      return;
    }

    if (($handle = fopen($filepath, 'r')) !== FALSE) {
      $header = fgetcsv($handle); // Read header row
      while (($row = fgetcsv($handle)) !== FALSE) {
        $data = array_combine($header, $row);
        $block_id = $data['block_id'] ?? NULL;
        $state = $data['state'] ?? NULL;

        if ($block_id && $state) {
          $block = Block::load($block_id);
          if ($block) {
            if ($state === 'enabled') {
              $block->enable()->save();
              $this->logger()->success("Block $block_id enabled.");
            }
            elseif ($state === 'disabled') {
              $block->disable()->save();
              $this->logger()->success("Block $block_id disabled.");
            }
            else {
              $this->logger()->warning("Invalid state '$state' for block $block_id. Use enabled/disabled.");
            }
          }
          else {
            $this->logger()->warning("Block $block_id not found.");
          }
        }
      }
      fclose($handle);
    }
  }

}
