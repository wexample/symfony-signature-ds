<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\EmailInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * A signer added to a draft, by its address: an account where the
 * application finds one, someone outside otherwise.
 */
class EnvelopeSignerForm extends AbstractForm
{
    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('email', EmailInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => true,
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('name', TextInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => true,
                'constraints' => [new NotBlank()],
            ]);

        $this->builderAddSubmit($builder);
    }
}
