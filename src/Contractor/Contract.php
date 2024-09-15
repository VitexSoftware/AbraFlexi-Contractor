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

/**
 * Description of Contract.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class Contract extends \AbraFlexi\Smlouva {

    public function __construct($init, $options = []) {
        $this->defaultUrlParams = [
            'relations' => 'polozkySmlouvy,prilohy,udalosti,ucely',
            'detail' => 'full',
        ];
        $this->nativeTypes = false;
        parent::__construct($init, $options);
    }

    public function loadFromAbraFlexi($id = null) {
        parent::loadFromAbraFlexi($id);
        $firma = $this->getDataValue('firma');
        if ($firma) {
            $address = new \AbraFlexi\Adresar(\AbraFlexi\Functions::code($firma), ['nativeTypes' => false]);
            $this->setDataValue('firma', $address->getData());
        }
    }
}
