<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\OptIn;

interface OptInInterface
{
    /**
     * Creates a new double opt-in token.
     */
    public function create(string $table, int $id, string $email, string $subject, string $text): string;

    /**
     * Confirms a double opt-in token.
     */
    public function confirm(string $token): void;

    /**
     * Sends a double opt-in token via e-mail.
     */
    public function sendMail(string $token): void;

    /**
     * Flags a double opt-in token for removal.
     */
    public function flagForRemoval(string $token, int $removeOn): void;

    /**
     * Purges double opt-in tokens.
     *
     * Automatically remove confirmed tokens once they reach their "removeOn" date
     * and unconfirmed tokens toghether with their related record after 24 hours.
     */
    public function purgeTokens(): void;
}
