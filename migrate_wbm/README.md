# migrate_wbm
Drupal custom module to migrate Workbench Moderation & Workbench Email to Content Moderation.

Installation:
1. Place the `migrate_wbm` folder in `web/modules/custom/`.
2. Enable the module: `drush en migrate_wbm -y`
3. Run the migration: `drush migrate_wbm:run` (alias: `drush mwbm`)
