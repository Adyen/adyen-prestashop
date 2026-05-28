<?php

namespace AdyenPayment\Classes\Models;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 *
 * @ORM\Table(
 *     name="ps_adyen_entity",
 *     indexes={
 *
 *              @ORM\Index(name="type", columns={"type"})
 *          }
 *      )
 */
class AdyenEntity extends BaseEntity
{
}
