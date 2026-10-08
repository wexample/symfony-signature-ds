<?php

namespace Wexample\SymfonySignatureDs\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;
use Wexample\SymfonyMediaDs\Form\MediaPickerType;

/**
 * A new envelope: its title, its documents picked in the media library, and
 * whether its signers sign in order. Its signers are added on its page.
 */
class EnvelopeCreateForm extends AbstractForm
{
    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('title', TextInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => true,
                'constraints' => [new NotBlank()],
            ])
            ->add('documents', MediaPickerType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                'multiple' => true,
                'target' => 'panel',
                'constraints' => [new Count(min: 1)],
            ])
            ->add('ordered', SwitchInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => false,
                'help' => true,
            ]);

        $this->builderAddSubmit($builder);
    }
}
