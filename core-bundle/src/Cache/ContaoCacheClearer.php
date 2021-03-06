<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Cache;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class ContaoCacheClearer implements CacheClearerInterface
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function clear($cacheDir): void
    {
        $this->filesystem->remove($cacheDir.'/contao/config');
        $this->filesystem->remove($cacheDir.'/contao/dca');
        $this->filesystem->remove($cacheDir.'/contao/languages');
        $this->filesystem->remove($cacheDir.'/contao/sql');
    }
}
