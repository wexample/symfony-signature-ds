<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;
use Wexample\SymfonyForms\Controller\FormController;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonySignature\Exception\SignatureException;
use Wexample\SymfonySignature\Service\EnvelopeService;
use Wexample\SymfonySignatureDs\Service\EnvelopeAccessService;

/**
 * A form about one envelope or one signer, which the submission url names
 * by id. The change is made in formIsValid(), so that a refusal comes back
 * as an error on the form.
 *
 * @template T of object
 */
abstract class AbstractEnvelopeFormProcessor extends AbstractFormProcessor
{
    /** The refusal code is appended: `@form::error.refused.not_your_turn`. */
    public const string ERROR_REFUSED = '@form::error.refused.';

    /** @var T|null */
    protected ?object $target = null;

    /** The request signed or declined from: its address and browser are kept. */
    protected readonly RequestStack $requests;

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        protected readonly Security $security,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly EnvelopeService $envelopes,
        protected readonly EnvelopeAccessService $access,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);

        $this->requests = $requestStack;
    }

    /**
     * The envelope of the submission handled, not of the next visitor's.
     */
    public function reset(): void
    {
        parent::reset();
        $this->target = null;
    }

    /**
     * @return class-string<T>
     */
    abstract protected function getTargetClass(): string;

    /**
     * @param T $target
     */
    abstract protected function mayAct(object $target): bool;

    /**
     * @throws SignatureException
     */
    abstract protected function apply(FormInterface $form): void;

    public function getFormActionRoute(): string
    {
        return 'form_' . FormController::ROUTE_ENTITY_FORM_PROCESSOR_SUBMIT;
    }

    public function getFormActionArgs($data): array
    {
        return parent::getFormActionArgs($data) + ['id' => (string) $this->target?->getId()];
    }

    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        $this->target = $data;

        return parent::createForm(null, $options + $this->getFormOptions());
    }

    /**
     * @return T
     */
    public function handleSubmittedRequest(Request $request): object
    {
        try {
            $target = $this->entityManager->find($this->getTargetClass(), (string) $request->attributes->get('id'));
        } catch (Throwable) {
            $target = null;
        }

        // Another's envelope does not exist, as far as this form knows.
        if (! $target || ! $this->mayAct($target)) {
            throw new NotFoundHttpException();
        }

        return $target;
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! parent::formIsValid($form)) {
            return false;
        }

        try {
            $this->apply($form);
        } catch (SignatureException $exception) {
            $form->addError(new FormError(self::ERROR_REFUSED . $exception->refusal->value));

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormOptions(): array
    {
        return [];
    }
}
