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

/**
 * Locale modifier to be used to modify routePath directly.
 */
class LocaleModifier implements Modifiable
{
    use SiteLanguageAwareTrait;

    /**
     * @var array
     */
    protected $localeMap;

    /**
     * @var ?string
     */
    protected $default;

    /**
     * @param array $localeMap
     * @param string|null $default
     */
    public function __construct(array $localeMap, string $default = null)
    {
        $this->localeMap = $localeMap;
        $this->default = $default;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            'localeMap' => $this->localeMap,
            'default' => $this->default,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->localeMap = $data['localeMap'] ?? [];
        $this->default = $data['default'] ?? null;
    }

    /**
     * @return null|string
     */
    public function retrieve(): ?string
    {
        $locale = $this->siteLanguage->getLocale();
        foreach ($this->localeMap as $item) {
            $pattern = '#' . $item['locale'] . '#i';
            if (preg_match($pattern, $locale)) {
                return (string)$item['value'];
            }
        }
        return $this->default;
    }
}
