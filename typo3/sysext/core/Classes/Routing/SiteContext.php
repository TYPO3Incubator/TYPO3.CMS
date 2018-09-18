<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing;

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

use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class SiteContext implements \Serializable
{
    /**
     * @var Site
     */
    protected $site;

    /**
     * @var SiteLanguage
     */
    protected $siteLanguage;

    public function __construct(
        Site $site,
        SiteLanguage $siteLanguage
        // @todo int $pageId (maybe)
    )
    {
        $this->site = $site;
        $this->siteLanguage = $siteLanguage;
    }

    public function serialize()
    {
        return serialize([
            'site' => [
                'identifier' => $this->site->getIdentifier(),
                'rootPageId' => $this->site->getRootPageId(),
                'configuration' => $this->site->getConfiguration(),
            ],
            'siteLanguage' => $this->siteLanguage->toArray(),
        ]);
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized, ['allowed_classes' => false]);
        $this->site = new Site(
            $data['site']['identifier'],
            $data['site']['rootPageId'],
            $data['site']['configuration']
        );
        $this->siteLanguage = new SiteLanguage(
            $data['siteLanguage']['languageId'],
            $data['siteLanguage']['locale'],
            new Uri($data['siteLanguage']['base']),
            $data['siteLanguage']
        );
    }

    /**
     * @return Site
     */
    public function getSite(): Site
    {
        return $this->site;
    }

    /**
     * @return SiteLanguage
     */
    public function getSiteLanguage(): SiteLanguage
    {
        return $this->siteLanguage;
    }
}
