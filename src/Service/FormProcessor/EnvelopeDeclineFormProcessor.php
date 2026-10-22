<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Routing\SignatureRoute;

/**
 * Declining, for whoever may sign as the signer.
 *
 * @extends AbstractEnvelopeFormProcessor<EnvelopeSigner>
 */
class EnvelopeDeclineFormProcessor extends AbstractEnvelopeFormProcessor
{
    public function getRequiredRoles(): array
    {
        return [];
    }

    protected function getTargetClass(): string
    {
        return EnvelopeSigner::class;
    }

    protected function mayAct(object $target): bool
    {
        return $this->access->hasOpenedLink($target);
    }

    protected function apply(FormInterface $form): void
    {
        $reason = trim((string) $form->get('reason')->getData());
        $this->envelopes->decline($this->target, $reason === '' ? null : $reason, $this->requests->getCurrentRequest());
    }

    public function onValid(FormInterface $form)
    {
        $this->redirect((string) $this->requests->getCurrentRequest()?->headers->get('referer')
            ?: $this->urlGenerator->generate(SignatureRoute::SIGN, ['signer' => (string) $this->target->getId()]));
    }
}
