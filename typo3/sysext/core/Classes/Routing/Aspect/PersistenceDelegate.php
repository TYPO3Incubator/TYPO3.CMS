<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Aspect;

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

use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class PersistenceDelegate implements Delegable
{
    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @var \Closure
     */
    protected $resolveModifier;

    /**
     * @var \Closure
     */
    protected $generateModifier;

    /**
     * @param QueryBuilder $queryBuilder
     * @param \Closure $resolveModifier
     * @param \Closure $generateModifier
     */
    public function __construct(QueryBuilder $queryBuilder, \Closure $resolveModifier, \Closure $generateModifier)
    {
        $this->queryBuilder = $queryBuilder;
        $this->resolveModifier = $resolveModifier;
        $this->generateModifier = $generateModifier;
    }

    /**
     * @return int
     */
    public function exists(string $value): bool
    {
        $this->applyValueModifier($this->resolveModifier, $value);
        return $this->queryBuilder
            ->count('*')
            ->execute()
            ->fetchColumn(0) > 0;
    }

    /**
     * @return string
     */
    public function resolve(string $value): string
    {
        $this->applyValueModifier($this->resolveModifier, $value);
        return (string)$this->queryBuilder
            ->execute()
            ->fetchColumn(0);
    }

    /**
     * @return string
     */
    public function generate(string $value): string
    {
        $this->applyValueModifier($this->generateModifier, $value);
        return (string)$this->queryBuilder
            ->execute()
            ->fetchColumn(0);
    }

    /**
     * @param \Closure $modifier
     * @param string $value
     */
    protected function applyValueModifier(\Closure $modifier, string $value)
    {
        $modifier($this->queryBuilder, $value);
    }
}
