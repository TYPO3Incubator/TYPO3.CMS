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

class StaticRangeMapper implements Mappable, StaticMappable, \Countable
{
    /**
     * @var string
     */
    protected $start;

    /**
     * @var string
     */
    protected $end;

    /**
     * @var string[]
     */
    protected $range;

    /**
     * @param string $start
     * @param string $end
     */
    public function __construct(string $start, string $end)
    {
        $this->start = $start;
        $this->end = $end;
        $this->range = $this->buildRange();
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->range);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'start' => $this->start,
            'end' => $this->end,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->start = $data['start'] ?? '';
        $this->end = $data['end'] ?? '';
        $this->range = $this->buildRange();
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function generate(string $value): ?string
    {
        return $this->respondWhenInRange($value);
    }

    /**
     * @param string $value
     * @return null|string
     */
    public function resolve(string $value): ?string
    {
        return $this->respondWhenInRange($value);
    }

    /**
     * @param string $value
     * @return null|string
     */
    protected function respondWhenInRange(string $value): ?string
    {
        if (in_array($value, $this->range, true)) {
            return $value;
        }
        return null;
    }

    /**
     * Builds range based on given settings and ensures each item is string.
     * The amount of items is limited to 1000 in order to avoid brute-force
     * scenarios and the risk of cache-flooding.
     *
     * In case that is not enough, creating a custom and more specific mapper
     * is encouraged. Using high values that are not distinct exposes the site
     * to the risk of cache-flooding.
     *
     * @return string[]
     */
    protected function buildRange(): array
    {
        $range = array_map('strval', range($this->start, $this->end));
        if (count($range) > 1000) {
            throw new \LogicException(
                'Range is larger than 1000 items',
                1537696771
            );
        }
        return $range;
    }
}
