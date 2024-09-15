<?php

declare(strict_types=1);

/**
 * This file is part of the AbraflexiContractor package
 *
 * https://github.com/VitexSoftware/AbraFlexi-Contractor
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Contractor;

use Ease\TWB5\WebPage;

require_once \dirname(__DIR__).'/vendor/autoload.php';

session_start();

new \Ease\Locale(null, '../i18n', 'abraflexi-contractor');

$oPage = WebPage::singleton();

$authSessionId = $oPage->getRequestValue('authSessionId');
$companyUrl = $oPage->getRequestValue('companyUrl');

if ($authSessionId && $companyUrl) {
    $_SESSION['connection'] = \AbraFlexi\RO::companyUrlToOptions($companyUrl);
    $_SESSION['connection']['authSessionId'] = $authSessionId;
}

if (\array_key_exists('connection', $_SESSION)) {
    \define('ABRAFLEXI_URL', $_SESSION['connection']['url']);
    \define('ABRAFLEXI_AUTHSESSID', $_SESSION['connection']['authSessionId']);
    \define('ABRAFLEXI_COMPANY', $_SESSION['connection']['company']);
} else {
    $localCfg = '../testing/.env';

    if (file_exists($localCfg)) {
        $shared = \Ease\Shared::instanced();
        $shared->loadConfig($localCfg, true);
    } else {
        if (\Ease\WebPage::getRequestValue('kod')) {
            $oPage->addItem(new \Ease\TWB5\LinkButton(
                'JavaScript:self.close()',
                _('Session Expired'),
                'danger',
            ));
        } else {
            $oPage->redirect('install.php');
            echo $oPage->draw();

            exit;
        }
    }
}