<?php

namespace Wexample\SymfonySignatureDs\Service\FormProcessor;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonySignature\Routing\SignatureRoute;

/**
 * Hashes the file posted, then shows the envelopes holding it: the hash
 * goes in the url, the file nowhere.
 */
class SignatureVerifyFormProcessor extends AbstractFormProcessor
{
    /**
     * Anyone holding a file may ask whether it was signed.
     */
    public function getRequiredRoles(): array
    {
        return [];
    }

    public function onValid(FormInterface $form)
    {
        $file = $form->get('file')->getData();

        if ($file instanceof UploadedFile) {
            $this->redirectToRoute(SignatureRoute::VERIFY, ['sha256' => hash_file('sha256', $file->getPathname())]);
        }
    }
}
