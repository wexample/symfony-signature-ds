<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonySignature\Routing\SignatureRoute;
use Wexample\SymfonySignature\Service\PersonalSignatureService;

/**
 * The image the signed-in account signs with by default.
 */
class PersonalSignatureFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly PersonalSignatureService $signatures,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        return parent::createForm(['image' => $this->signatures->get($this->getUser())], $options);
    }

    public function onValid(FormInterface $form)
    {
        $this->signatures->set($this->getUser(), $form->get('image')->getData());
        $this->setNotification('@form::success.message');
        $this->redirectToRoute(SignatureRoute::PERSONAL);
    }

    private function getUser(): object
    {
        return $this->security->getUser() ?? throw new AccessDeniedHttpException();
    }
}
