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
 *     name="ps_adyen_queue",
 *     indexes={
 *
 *              @ORM\Index(name="index1", columns={"index_1"}),
 *              @ORM\Index(name="typeStatus", columns={"index_1", "index_2"}),
 *              @ORM\Index(name="statusQueuePriority", columns={"index_1", "index_3", "index_8"}),
 *              @ORM\Index(name="latestByType", columns={"index_2", "index_5"}),
 *          }
 *      )
 */
class QueueEntity extends BaseEntity
{
}
