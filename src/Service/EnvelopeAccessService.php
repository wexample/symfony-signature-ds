<?php

namespace Wexample\SymfonySignatureDs\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Service\SignatureKeyService;
use Wexample\SymfonySignature\Service\SignerAccessService;

/**
 * Who reaches what: an envelope, its sender and its account signers; a
 * signature page, its account signed in, or anyone holding the signed link
 * of an outside signer. Anyone else gets a 404, which does not say the
 * envelope exists.
 */
class EnvelopeAccessService
{
    private const string SESSION_PREFIX = 'signature.signer.';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly SignatureKeyService $keys,
        private readonly SignerAccessService $signerAccess,
    ) {
    }

    public function isOwner(Envelope $envelope): bool
    {
        $user = $this->security->getUser();

        return $user !== null && $this->keys->is($user, $envelope->getOwnerType(), $envelope->getOwnerId());
    }

    public function maySee(Envelope $envelope): bool
    {
        if ($this->isOwner($envelope)) {
            return true;
        }

        foreach ($envelope->getSigners() as $signer) {
            if ($this->isSignerAccount($signer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The signer this request acts as: its account signed in, or an outside
     * signer through a link still valid.
     */
    public function maySignAs(EnvelopeSigner $signer, Request $request): bool
    {
        if (! $signer->isOutside()) {
            return $this->isSignerAccount($signer);
        }

        if ($this->signerAccess->isValidLink($request)) {
            // The forms of the page post elsewhere, without the link: the
            // session remembers it was opened through it.
            $request->getSession()->set(self::SESSION_PREFIX . $signer->getId(), true);

            return true;
        }

        return $this->hasOpenedLink($signer);
    }

    /**
     * Whether this session opened the signed link of $signer: what a form of
     * its page, posted elsewhere, checks.
     */
    public function hasOpenedLink(EnvelopeSigner $signer): bool
    {
        $session = $this->requestStack->getSession();

        return $signer->isOutside()
            ? (bool) $session->get(self::SESSION_PREFIX . $signer->getId(), false)
            : $this->isSignerAccount($signer);
    }

    public function isSignerAccount(EnvelopeSigner $signer): bool
    {
        $user = $this->security->getUser();

        return $user !== null && ! $signer->isOutside() && $this->keys->is($user, $signer->getAccountType(), $signer->getAccountId());
    }
}
