<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Symfony\Component\Form\FormInterface;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Routing\SignatureRoute;

/**
 * A signer added to a draft by its sender.
 *
 * @extends AbstractEnvelopeFormProcessor<Envelope>
 */
class EnvelopeSignerFormProcessor extends AbstractEnvelopeFormProcessor
{
    protected function getTargetClass(): string
    {
        return Envelope::class;
    }

    protected function mayAct(object $target): bool
    {
        return $this->access->isOwner($target);
    }

    protected function apply(FormInterface $form): void
    {
        $this->envelopes->addSigner($this->target, (string) $form->get('email')->getData(), (string) $form->get('name')->getData());
    }

    public function onValid(FormInterface $form)
    {
        $this->redirectToRoute(SignatureRoute::ENVELOPE, ['id' => (string) $this->target->getId()]);
    }
}
