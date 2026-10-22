<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\FileInputType;

/**
 * A file, to find the envelopes it was signed in by its bytes. Hashed, then
 * forgotten: nothing of it is kept.
 */
class SignatureVerifyForm extends AbstractForm
{
    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add('file', FileInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            'constraints' => [new NotNull(), new File(maxSize: '50M')],
        ]);

        $this->builderAddSubmit($builder);
    }
}
