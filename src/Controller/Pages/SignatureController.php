<?php

namespace Wexample\SymfonySignatureDs\Controller\Pages;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyLoader\Service\AdaptiveRendererService;
use Wexample\SymfonyLoader\Service\PageService;
use Wexample\SymfonyMedia\Service\MediaStorage;
use Wexample\SymfonySignature\Entity\Envelope;
use Wexample\SymfonySignature\Entity\EnvelopeDocument;
use Wexample\SymfonySignature\Entity\EnvelopeSigner;
use Wexample\SymfonySignature\Enum\EnvelopeSignerStatus;
use Wexample\SymfonySignature\Enum\EnvelopeStatus;
use Wexample\SymfonySignature\Exception\SignatureException;
use Wexample\SymfonySignature\Repository\EnvelopeRepository;
use Wexample\SymfonySignature\Routing\SignatureRoute;
use Wexample\SymfonySignature\Service\EnvelopeProofService;
use Wexample\SymfonySignature\Service\EnvelopeService;
use Wexample\SymfonySignature\Service\EnvelopeVerifier;
use Wexample\SymfonySignature\Service\PersonalSignatureService;
use Wexample\SymfonySignature\Service\SignatureKeyService;
use Wexample\SymfonySignatureDs\Service\EnvelopeAccessService;
use Wexample\SymfonySignatureDs\Service\FormProcessor\EnvelopeCreateFormProcessor;
use Wexample\SymfonySignatureDs\Service\FormProcessor\EnvelopeDeclineFormProcessor;
use Wexample\SymfonySignatureDs\Service\FormProcessor\EnvelopeSignerFormProcessor;
use Wexample\SymfonySignatureDs\Service\FormProcessor\EnvelopeSignFormProcessor;
use Wexample\SymfonySignatureDs\Service\FormProcessor\PersonalSignatureFormProcessor;
use Wexample\SymfonySignatureDs\Service\FormProcessor\SignatureVerifyFormProcessor;
use Wexample\SymfonySignatureDs\Traits\SymfonySignatureDsBundleClassTrait;

/**
 * The envelopes of the signed-in account — sent, and to sign —, composing
 * one, its page, the page a signer signs on — signed in, or through the
 * signed link of an outside signer —, verifying a file, and one's own
 * signature.
 */
#[Route(path: '/signatures')]
final class SignatureController extends AbstractPagesController
{
    use SymfonySignatureDsBundleClassTrait;

    private const string MESSAGES_DONE = 'WexampleSymfonySignatureDsBundle.pages.signature.envelope::done.';

    private const string MESSAGES_REFUSED = 'WexampleSymfonySignatureDsBundle.forms.envelope_signer_form::error.refused.';

    /** The application's form token id, declared stateless already. */
    public const string CSRF_TOKEN_ID = 'submit';

    public function __construct(
        AdaptiveRendererService $adaptiveRendererService,
        PageService $pageService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($adaptiveRendererService, $pageService);
    }

    #[Route(path: '', name: SignatureRoute::INDEX)]
    #[IsGranted('IS_AUTHENTICATED')]
    public function index(EnvelopeRepository $repository, SignatureKeyService $keys, EnvelopeService $envelopes): Response
    {
        [$type, $id] = $keys->key($this->getUser());

        $toSign = [];

        foreach ($repository->findBySignerAccount($type, $id) as $envelope) {
            foreach ($envelope->getSigners() as $signer) {
                if ($keys->is($this->getUser(), $signer->getAccountType(), $signer->getAccountId())) {
                    $toSign[] = $this->describeSigner($signer, $envelopes);
                }
            }
        }

        return $this->renderPage('index', [
            'sent' => array_map(fn (Envelope $envelope) => $this->describe($envelope), $repository->findByOwner($type, $id)),
            'to_sign' => $toSign,
        ]);
    }

    #[Route(path: '/new', name: SignatureRoute::CREATE)]
    #[IsGranted('IS_AUTHENTICATED')]
    public function create(EnvelopeCreateFormProcessor $formProcessor): Response
    {
        return $this->renderPage('create', [
            'envelope_create_form' => $formProcessor->createForm()->createView(),
        ]);
    }

    #[Route(path: '/verify', name: SignatureRoute::VERIFY)]
    public function verify(Request $request, EnvelopeRepository $repository, EnvelopeAccessService $access, SignatureVerifyFormProcessor $formProcessor): Response
    {
        $sha256 = strtolower((string) $request->query->get('sha256'));
        $found = preg_match('/^[0-9a-f]{64}$/', $sha256)
            ? array_map(fn (EnvelopeDocument $document) => [
                'envelope' => $this->describe($document->getEnvelope()),
                // Its page to whoever may see it; to anyone else, that it was signed.
                'visible' => $access->maySee($document->getEnvelope()),
                'name' => $document->getMedia()->getName(),
            ], $repository->findDocumentsBySha256($sha256))
            : null;

        return $this->renderPage('verify', [
            'sha256' => $found === null ? null : $sha256,
            'found' => $found,
            'signature_verify_form' => $formProcessor->createForm()->createView(),
        ]);
    }

    #[Route(path: '/mine', name: SignatureRoute::PERSONAL)]
    #[IsGranted('IS_AUTHENTICATED')]
    public function personal(PersonalSignatureFormProcessor $formProcessor, PersonalSignatureService $signatures): Response
    {
        return $this->renderPage('personal', [
            'image' => $signatures->get($this->getUser()),
            'personal_signature_form' => $formProcessor->createForm()->createView(),
        ]);
    }

    #[Route(path: '/{id}', name: SignatureRoute::ENVELOPE, requirements: ['id' => '[0-9a-f-]{36}'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function envelope(
        string $id,
        EnvelopeAccessService $access,
        EnvelopeService $envelopes,
        EnvelopeVerifier $verifier,
        EnvelopeSignerFormProcessor $signerFormProcessor
    ): Response {
        $envelope = $this->findEnvelope($id, $access);
        $owner = $access->isOwner($envelope);
        $draft = $envelope->getStatus() === EnvelopeStatus::DRAFT;

        return $this->renderPage('envelope', [
            'envelope' => $this->describe($envelope),
            'owner' => $owner,
            'draft' => $draft,
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            'documents' => array_map(fn (EnvelopeDocument $document) => [
                'id' => (string) $document->getId(),
                'name' => $document->getMedia()->getName(),
                'size' => $document->getMedia()->getSize(),
                'sha256' => $document->getSha256(),
            ], $envelope->getDocuments()->toArray()),
            'signers' => array_map(fn (EnvelopeSigner $signer) => $this->describeSigner($signer, $envelopes) + [
                'mine' => $access->isSignerAccount($signer),
            ], $envelope->getSigners()->toArray()),
            'verification' => $draft ? null : $verifier->verify($envelope),
            'envelope_signer_form' => $owner && $draft ? $signerFormProcessor->createForm($envelope)->createView() : null,
        ]);
    }

    #[Route(path: '/{id}/seal', name: SignatureRoute::SEAL, methods: [Request::METHOD_POST])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function seal(string $id, Request $request, EnvelopeAccessService $access, EnvelopeService $envelopes): RedirectResponse
    {
        $envelope = $this->findOwnEnvelope($id, $request, $access);

        return $this->act($envelope, fn () => $envelopes->seal($envelope), 'sealed');
    }

    #[Route(path: '/{id}/cancel', name: SignatureRoute::CANCEL, methods: [Request::METHOD_POST])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function cancel(string $id, Request $request, EnvelopeAccessService $access, EnvelopeService $envelopes): RedirectResponse
    {
        $envelope = $this->findOwnEnvelope($id, $request, $access);

        return $this->act($envelope, fn () => $envelopes->cancel($envelope), 'cancelled');
    }

    #[Route(path: '/{id}/signers/{signer}/remove', name: SignatureRoute::SIGNER_REMOVE, methods: [Request::METHOD_POST])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function removeSigner(string $id, string $signer, Request $request, EnvelopeAccessService $access, EnvelopeService $envelopes): RedirectResponse
    {
        $envelope = $this->findOwnEnvelope($id, $request, $access);
        $target = $envelope->getSigners()->findFirst(static fn ($key, EnvelopeSigner $item) => (string) $item->getId() === $signer)
            ?? throw $this->createNotFoundException();

        return $this->act($envelope, fn () => $envelopes->removeSigner($target), 'signer_removed');
    }

    /**
     * The proof to keep apart from the application, as a file.
     */
    #[Route(path: '/{id}/proof', name: SignatureRoute::PROOF)]
    #[IsGranted('IS_AUTHENTICATED')]
    public function proof(string $id, EnvelopeAccessService $access, EnvelopeProofService $proofs): JsonResponse
    {
        $envelope = $this->findEnvelope($id, $access);
        $response = new JsonResponse($proofs->build($envelope), json: false);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'signature-proof-' . $envelope->getId() . '.json'
        ));

        return $response;
    }

    /**
     * Where a signer signs: its account signed in, or the signed link of an
     * outside signer. Anyone else, a 404.
     */
    #[Route(path: '/sign/{signer}', name: SignatureRoute::SIGN, requirements: ['signer' => '[0-9a-f-]{36}'])]
    public function sign(
        string $signer,
        Request $request,
        EnvelopeAccessService $access,
        EnvelopeService $envelopes,
        EnvelopeSignFormProcessor $signFormProcessor,
        EnvelopeDeclineFormProcessor $declineFormProcessor,
        PersonalSignatureService $personalSignatures,
        UriSigner $uriSigner
    ): Response {
        $target = $this->findSigner($signer);

        if (! $target || ! $access->maySignAs($target, $request)) {
            throw $this->createNotFoundException();
        }

        $envelope = $target->getEnvelope();
        $awaited = in_array($target, $envelopes->getAwaited($envelope), true);

        return $this->renderPage('sign', [
            'envelope' => $this->describe($envelope),
            'signer' => $this->describeSigner($target, $envelopes),
            'awaited' => $awaited,
            'personal_image' => $target->isOutside() ? null : $personalSignatures->get($this->getUser()),
            'documents' => array_map(function (EnvelopeDocument $document) use ($target, $uriSigner) {
                $url = $this->generateUrl(SignatureRoute::DOCUMENT, ['signer' => (string) $target->getId(), 'document' => (string) $document->getId()], 0);

                return [
                    'name' => $document->getMedia()->getName(),
                    'size' => $document->getMedia()->getSize(),
                    // An outside signer reads them through signed links too.
                    'url' => $target->isOutside() ? $uriSigner->sign($url, $target->getEnvelope()->getExpiresAt()) : $url,
                ];
            }, $envelope->getDocuments()->toArray()),
            'envelope_sign_form' => $awaited ? $signFormProcessor->createForm($target)->createView() : null,
            'envelope_decline_form' => $awaited ? $declineFormProcessor->createForm($target)->createView() : null,
        ]);
    }

    /**
     * A document of the envelope, for a signer to read: always downloaded,
     * never drawn by the browser.
     */
    #[Route(path: '/sign/{signer}/document/{document}', name: SignatureRoute::DOCUMENT)]
    public function document(string $signer, string $document, Request $request, EnvelopeAccessService $access, UriSigner $uriSigner, MediaStorage $storage): BinaryFileResponse
    {
        $target = $this->findSigner($signer);
        $allowed = $target && ($target->isOutside() ? $uriSigner->checkRequest($request) : $access->isSignerAccount($target));
        $item = $allowed
            ? $target->getEnvelope()->getDocuments()->findFirst(static fn ($key, EnvelopeDocument $doc) => (string) $doc->getId() === $document)
            : null;

        if (! $item) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($storage->getPath($item->getMedia()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $item->getMedia()->getName());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', 'sandbox');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Envelope $envelope): array
    {
        return [
            'id' => (string) $envelope->getId(),
            'title' => $envelope->getTitle(),
            'status' => $envelope->getStatus()->value,
            'ordered' => $envelope->isOrdered(),
            'fingerprint' => $envelope->getFingerprint(),
            'date_created' => $envelope->getDateCreated(),
            'date_sealed' => $envelope->getDateSealed(),
            'date_closed' => $envelope->getDateClosed(),
            'documents_count' => $envelope->getDocuments()->count(),
            'signers_count' => $envelope->getSigners()->count(),
            'signed_count' => $envelope->getSigners()->filter(static fn (EnvelopeSigner $signer) => $signer->getStatus() === EnvelopeSignerStatus::SIGNED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeSigner(EnvelopeSigner $signer, EnvelopeService $envelopes): array
    {
        return [
            'id' => (string) $signer->getId(),
            'envelope_id' => (string) $signer->getEnvelope()->getId(),
            'envelope_title' => $signer->getEnvelope()->getTitle(),
            'position' => $signer->getPosition(),
            'name' => $signer->getName(),
            'email' => $signer->getEmail(),
            'outside' => $signer->isOutside(),
            'status' => $signer->getStatus()->value,
            'awaited' => in_array($signer, $envelopes->getAwaited($signer->getEnvelope()), true),
            'date_signed' => $signer->getDateSigned(),
            'hash' => $signer->getHash(),
            'timestamped' => $signer->getTimestampToken() !== null,
            'decline_reason' => $signer->getDeclineReason(),
            'image_id' => $signer->getSignatureImage() ? (string) $signer->getSignatureImage()->getId() : null,
        ];
    }

    private function findEnvelope(string $id, EnvelopeAccessService $access): Envelope
    {
        $envelope = $this->findEntity(Envelope::class, $id);

        if (! $envelope instanceof Envelope || ! $access->maySee($envelope)) {
            throw $this->createNotFoundException();
        }

        return $envelope;
    }

    private function findOwnEnvelope(string $id, Request $request, EnvelopeAccessService $access): Envelope
    {
        // A button, not a form the browser fills: the token tells a click on
        // the page from a click somewhere else.
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $envelope = $this->findEnvelope($id, $access);

        if (! $access->isOwner($envelope)) {
            throw $this->createNotFoundException();
        }

        return $envelope;
    }

    private function findSigner(string $id): ?EnvelopeSigner
    {
        $signer = $this->findEntity(EnvelopeSigner::class, $id);

        return $signer instanceof EnvelopeSigner ? $signer : null;
    }

    private function findEntity(string $class, string $id): ?object
    {
        try {
            return $this->entityManager->find($class, $id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Runs an action on the envelope and goes back to it, what came of it said
     * in a toast on the page reached — a flash, read once —, never in the
     * address, where a reload or a shared link would say it again.
     */
    private function act(Envelope $envelope, callable $action, string $done): RedirectResponse
    {
        try {
            $action();
            $this->notify('success', self::MESSAGES_DONE.$done);
        } catch (SignatureException $exception) {
            $this->notify('warning', self::MESSAGES_REFUSED.$exception->refusal->value);
        }

        return $this->redirectToRoute(SignatureRoute::ENVELOPE, ['id' => (string) $envelope->getId()]);
    }

    private function notify(
        string $type,
        string $message
    ): void {
        $this->addFlash($type, ['type' => $type, 'message' => $message]);
    }
}
