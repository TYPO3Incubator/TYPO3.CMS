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

class SlugMapper implements Mappable
{
    /**
     * @var SiteContext
     */
    protected $context;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var string
     */
    protected $fieldName;

    public function __construct(SiteContext $context, string $tableName, string $fieldName)
    {
        $this->context = $context;
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'tableName' => $this->tableName,
            'fieldName' => $this->fieldName,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->tableName = $data['tableName'] ?? '';
        $this->fieldName = $data['fieldName'] ?? '';
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
