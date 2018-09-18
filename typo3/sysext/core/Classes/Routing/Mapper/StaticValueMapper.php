<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Mapper;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Routing\SiteContext;

class StaticValueMapper implements Mappable
{
    /**
     * @var SiteContext
     */
    protected $context;

    /**
     * @var array
     */
    protected $map;

    /**
     * @param SiteContext $context
     * @param array $map
     */
    public function __construct(SiteContext $context, array $map)
    {
        $this->context = $context;
        $this->map = $map;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'map' => $this->map,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->map = $data['map'] ?? [];
    }

    public function getRegularExpression(): string
    {
    }

    public function getCondition(): string
    {
    }

    public function generate(string $value): string
    {
    }

    public function resolve(string $value): string
    {
    }
}
