<?php
/**
 * DbCleaner Cron Job Controller
 *
 * @author    Mr-dev
 * @copyright 2025 Mr-dev
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

class DbCleanerCronModuleFrontController extends ModuleFrontController
{
  public function init()
  {
    parent::init();

    // Security check: Validate the token
    $token = Tools::getValue('token');
    $stored_token = Configuration::get('DBCLEANER_CRON_TOKEN');

    if (!$token || !$stored_token || !hash_equals($stored_token, $token)) {
      PrestaShopLogger::addLog('DbCleaner Cron: Invalid or missing token.', 3);
      header('HTTP/1.1 403 Forbidden');
      echo 'Access Forbidden.';
      exit;
    }

    // Execute the cleanup
    /** @var DbCleaner $module */
    $module = $this->module;
    if ($module instanceof DbCleaner) {
      if ($module->runCleanup()) {

        die('OK');
      } else {
        PrestaShopLogger::addLog('DbCleaner Cron: Cleanup failed.', 3);
        header('HTTP/1.1 500 Internal Server Error');
        echo 'DbCleaner Cron: Cleanup failed. Check logs.';
        exit;
      }
    } else {
      PrestaShopLogger::addLog('DbCleaner Cron: Module instance not found.', 3);
      header('HTTP/1.1 500 Internal Server Error');
      echo 'DbCleaner Cron: Module error.';
      exit;
    }
  }


}
