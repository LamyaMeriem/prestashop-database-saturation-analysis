<?php
/**
 * DbCleaner - Cleans specific database tables in PrestaShop.
 *
 * @author    Mr-dev 
 * @copyright 2024 Mr-dev
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
  exit;
}

class DbCleaner extends Module
{
  public function __construct()
  {
    $this->name = 'dbcleaner';
    $this->tab = 'administration';
    $this->version = '1.0.0';
    $this->author = 'Mr-dev';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    $this->bootstrap = true;

    parent::__construct();

    $this->displayName = $this->l('Database Cleaner');
    $this->description = $this->l('Cleans old connection and guest data from the database.');

    $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
  }

  public function install()
  {
    return parent::install() && $this->generateCronToken();
  }

  public function uninstall()
  {
    Configuration::deleteByName('DBCLEANER_CRON_TOKEN');
    return parent::uninstall();
  }

  protected function generateCronToken()
  {
    $token = Tools::passwdGen(32);
    return Configuration::updateValue('DBCLEANER_CRON_TOKEN', $token);
  }

  public function getContent()
  {
    $output = '';
    if (Tools::isSubmit('submitDbCleanManual')) {
      if ($this->runCleanup()) {
        $output .= $this->displayConfirmation($this->l('Database tables cleaned successfully.'));
      } else {
        $output .= $this->displayError($this->l('An error occurred during database cleaning. Check PrestaShop logs for details.'));
      }
    }

    return $output . $this->renderConfigurationForm();
  }

  public function renderConfigurationForm()
  {
    $cron_url = $this->context->link->getModuleLink(
      $this->name,
      'cron',
      ['token' => Configuration::get('DBCLEANER_CRON_TOKEN')],
      true
    );

    $fields_form[0]['form'] = [
      'legend' => [
        'title' => $this->l('Manual Cleaning'),
        'icon' => 'icon-cogs',
      ],
      'input' => [
        [
          'type' => 'html',
          'name' => 'manual_info',
          'html_content' => '<p>' . $this->l('Click the button below to manually clean the database tables now.') . '</p>',
        ],
      ],
      'submit' => [
        'title' => $this->l('Clean Database Now'),
        'class' => 'btn btn-primary pull-right',
        'name' => 'submitDbCleanManual',
        'icon' => 'process-icon-database mr-1'
      ],
    ];

    $fields_form[1]['form'] = [
      'legend' => [
        'title' => $this->l('Automatic Cleaning (Cron Job)'),
        'icon' => 'icon-time',
      ],
      'input' => [
        [
          'type' => 'html',
          'name' => 'cron_info',
          'html_content' => '<p>' . $this->l('To automate the cleaning process, set up a cron job on your server to run daily (e.g., at 1 AM).') . '</p>'
            . '<p>' . $this->l('Use the following URL for your cron task:') . '</p>'
            . '<div class="alert alert-info">' . $cron_url . '</div>'
            . '<p><strong>' . $this->l('Example Cron Command (run `crontab -e` in your server terminal):') . '</strong></p>'
            . '<pre><code>0 1 * * * wget -O - -q "' . $cron_url . '" > /dev/null 2>&1</code></pre>'
            . '<p><small>' . $this->l('Note: The exact command might vary depending on your hosting environment. `wget` or `curl` are common choices. The example runs the task every day at 1:00 AM. `> /dev/null 2>&1` prevents email output.') . '</small></p>'
            . '<p><small>' . $this->l('Make sure your server\'s IP is not blocked and can access this URL.') . '</small></p>',
        ],
      ],
    ];


    $helper = new HelperForm();
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
    $helper->title = $this->displayName;
    $helper->show_toolbar = false;
    $helper->submit_action = 'submit' . $this->name;

    return $helper->generateForm($fields_form);
  }

  public function runCleanup()
  {
    $success = true;
    $db = Db::getInstance();

    try {
      $sql1 = 'DELETE FROM `' . _DB_PREFIX_ . 'connections` WHERE `date_add` < DATE_SUB(NOW(), INTERVAL 1 DAY)';
      if (!$db->execute($sql1)) {
        PrestaShopLogger::addLog('DbCleaner: Failed to execute query: ' . $sql1 . ' - Error: ' . $db->getMsgError(), 3);
        $success = false;
      }

      $sql2 = 'DELETE FROM `' . _DB_PREFIX_ . 'connections_source` WHERE `id_connections` NOT IN (SELECT `id_connections` FROM `' . _DB_PREFIX_ . 'connections`)';
      if ($success && !$db->execute($sql2)) {
        PrestaShopLogger::addLog('DbCleaner: Failed to execute query: ' . $sql2 . ' - Error: ' . $db->getMsgError(), 3);
        $success = false;
      }
      $sql3 = 'DELETE FROM `' . _DB_PREFIX_ . 'guest` WHERE `id_guest` NOT IN (SELECT `id_guest` FROM `' . _DB_PREFIX_ . 'connections`)';
      if ($success && !$db->execute($sql3)) {
        PrestaShopLogger::addLog('DbCleaner: Failed to execute query: ' . $sql3 . ' - Error: ' . $db->getMsgError(), 3);
        $success = false;
      }

    } catch (PrestaShopDatabaseException $e) {
      PrestaShopLogger::addLog('DbCleaner: Database exception during cleanup: ' . $e->getMessage(), 3);
      $success = false;
    } catch (Exception $e) {
      PrestaShopLogger::addLog('DbCleaner: General exception during cleanup: ' . $e->getMessage(), 3);
      $success = false;
    }

    if ($success) {
      PrestaShopLogger::addLog('DbCleaner: Cleanup executed successfully.', 1);
    }

    return $success;
  }
}
