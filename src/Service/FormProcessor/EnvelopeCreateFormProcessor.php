<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonySignature\Routing\SignatureRoute;
use Wexample\SymfonySignature\Service\EnvelopeService;

/**
 * A new envelope, sent by the signed-in account; then its page, to add its
 * signers.
 */
class EnvelopeCreateFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly EnvelopeService $envelopes,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function onValid(FormInterface $form)
    {
        $envelope = $this->envelopes->create(
            (string) $form->get('title')->getData(),
            $this->security->getUser(),
            [],
            null,
            $form->get('ordered')->getData() === true
        );

        foreach ($form->get('documents')->getData() ?? [] as $media) {
            $this->envelopes->addDocument($envelope, $media);
        }

        $this->redirectToRoute(SignatureRoute::ENVELOPE, ['id' => (string) $envelope->getId()]);
    }
}
