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

use TYPO3\CMS\Core\Routing\Traits\SiteLanguageAwareTrait;

class StaticValueMapper implements Mappable, StaticMappable, \Countable
{
    use SiteLanguageAwareTrait;

    /**
     * @var array
     */
    protected $map;

    /**
     * @var array|null
     */
    protected $localeMap;

    /**
     * @param array $map
     * @param array|null $localeMap
     */
    public function __construct(array $map, array $localeMap = null)
    {
        $this->map = $map;
        $this->localeMap = $localeMap;
    }

    public function count(): int
    {
        return count($this->retrieveLocaleMap() ?? $this->map);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'map' => $this->map,
            'localeMap' => $this->localeMap,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->map = $data['map'] ?? [];
        $this->localeMap = $data['localeMap'] ?? null;
    }

    public function generate(string $value): ?string
    {
        $map = $this->retrieveLocaleMap() ?? $this->map;
        $index = array_search($value, $map, true);
        return $index !== false ? (string)$index : null;
    }

    public function resolve(string $value): ?string
    {
        $map = $this->retrieveLocaleMap() ?? $this->map;
        return isset($map[$value]) ? (string)$map[$value] : null;
    }

    protected function retrieveLocaleMap(): ?array
    {
        $locale = $this->siteLanguage->getLocale();
        foreach ($this->localeMap as $item) {
            $pattern = '#' . $item['locale'] . '#i';
            if (preg_match($pattern, $locale)) {
                return $item['map'];
            }
        }
        return null;
    }
}
