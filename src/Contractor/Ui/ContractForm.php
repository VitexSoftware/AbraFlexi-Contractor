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

namespace AbraFlexi\Contractor\Ui;

use AbraFlexi\Contractor\Contract;
use ByJG\JinjaPhp\Template;

class ContractForm extends \Ease\TWB5\Form {

    private Contract $contract;

    public function __construct(Contract $contract) {
        parent::__construct();
        $this->contract = $contract;

        $templateString = file_get_contents('/home/vitex/Projects/VitexSoftware/AbraFlexi-Contractor/templates/contract.j2');

        $template = new Template($templateString);
        $template->withUndefined(new \ByJG\JinjaPhp\Undefined\DebugUndefined());  // Default is StrictUndefined

        $contractData = ['contract' => $contract->getData()];
        
        $this->addItem($template->render($contractData));
    }
}
