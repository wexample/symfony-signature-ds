<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyMedia\Entity\Media;
use Wexample\SymfonyMedia\Entity\MediaFolder;
use Wexample\SymfonyMedia\Service\MediaLibraryService;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Routing\SignatureRoute;
use Wexample\SymfonySignature\Service\EnvelopeService;
use Wexample\SymfonySignature\Service\PersonalSignatureService;
use Wexample\SymfonySignatureDs\Form\EnvelopeSignForm;
use Wexample\SymfonySignatureDs\Service\EnvelopeAccessService;

/**
 * A signature: by an account signed in, or an outside signer whose session
 * opened its signed link. Open to anyone else at the door, then refused as
 * if the signer did not exist.
 *
 * @extends AbstractEnvelopeFormProcessor<EnvelopeSigner>
 */
class EnvelopeSignFormProcessor extends AbstractEnvelopeFormProcessor
{
    /** Where an image uploaded to sign with is kept, in the media library. */
    public const string FOLDER = 'Signatures';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        Security $security,
        EntityManagerInterface $entityManager,
        EnvelopeService $envelopes,
        EnvelopeAccessService $access,
        private readonly PersonalSignatureService $personalSignatures,
        private readonly MediaStorage $storage,
        private readonly MediaLibraryService $library,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator, $security, $entityManager, $envelopes, $access);
    }

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

    protected function getFormOptions(): array
    {
        return [EnvelopeSignForm::OPTION_HAS_PERSONAL => $this->getPersonalSignature() !== null];
    }

    protected function apply(FormInterface $form): void
    {
        $this->envelopes->sign($this->target, $this->getImage($form), $this->requests->getCurrentRequest());
    }

    public function onValid(FormInterface $form)
    {
        $this->redirect((string) $this->requests->getCurrentRequest()?->headers->get('referer')
            ?: $this->urlGenerator->generate(SignatureRoute::SIGN, ['signer' => (string) $this->target->getId()]));
    }

    /**
     * An image uploaded here wins; else the signer's own, when it chose to
     * use it; else none.
     */
    private function getImage(FormInterface $form): ?Media
    {
        $upload = $form->get('image')->getData();

        if ($upload instanceof UploadedFile) {
            return $this->storage->import($upload->getPathname(), $upload->getClientOriginalName(), $this->getFolder());
        }

        if ($form->has('use_personal') && $form->get('use_personal')->getData() === true) {
            return $this->getPersonalSignature();
        }

        return null;
    }

    private function getPersonalSignature(): ?Media
    {
        $user = $this->security->getUser();

        return $user && $this->target && ! $this->target->isOutside() ? $this->personalSignatures->get($user) : null;
    }

    private function getFolder(): MediaFolder
    {
        return $this->entityManager->getRepository(MediaFolder::class)->findOneBy(['name' => self::FOLDER, 'parent' => null])
            ?? $this->library->createFolder(self::FOLDER);
    }
}
