<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Form\Type;

use Doctrine\DBAL\Connection;
use SaifulFeroz\Select2TableBundle\Form\DataTransformer\EntitiesToPropertyTransformer;
use SaifulFeroz\Select2TableBundle\Form\DataTransformer\EntityToPropertyTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\RouterInterface;

class Select2TableType extends AbstractType
{
    public function __construct(
        private readonly ?Connection $connection,
        private readonly RouterInterface $router,
        private readonly array $config = []
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($this->connection === null && empty($options['transformer'])) {
            throw new \LogicException('Doctrine DBAL connection is required for Select2TableType when no custom transformer is provided.');
        }

        if (!empty($options['transformer'])) {
            if (!\is_string($options['transformer'])) {
                throw new \InvalidArgumentException('The option "transformer" must be a string representing a valid class name.');
            }
            if (!class_exists($options['transformer'])) {
                throw new \InvalidArgumentException(sprintf('Unable to load transformer class: "%s".', $options['transformer']));
            }

            $transformer = new $options['transformer'](
                $this->connection,
                $options['table_name'],
                $options['text_property'],
                $options['primary_key']
            );

            if (!$transformer instanceof DataTransformerInterface) {
                throw new \LogicException(
                    sprintf(
                        'The custom transformer "%s" must implement "%s".',
                        $transformer::class,
                        DataTransformerInterface::class
                    )
                );
            }
        } else {
            $newTagPrefix = $options['allow_add']['new_tag_prefix'] ?? ($this->config['allow_add']['new_tag_prefix'] ?? '__');
            $newTagText = $options['allow_add']['new_tag_text'] ?? ($this->config['allow_add']['new_tag_text'] ?? ' (NEW)');

            $transformer = $options['multiple']
                ? new EntitiesToPropertyTransformer(
                    $this->connection,
                    $options['table_name'],
                    $options['text_property'],
                    $options['primary_key'],
                    $newTagPrefix,
                    $newTagText
                )
                : new EntityToPropertyTransformer(
                    $this->connection,
                    $options['table_name'],
                    $options['text_property'],
                    $options['primary_key'],
                    $newTagPrefix,
                    $newTagText
                );
        }

        $builder->addViewTransformer($transformer, true);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        parent::finishView($view, $form, $options);

        $view->vars['remote_path'] = $options['remote_path']
            ?: ($options['remote_route'] ? $this->router->generate(
                $options['remote_route'],
                array_merge($options['remote_params'] ?? [], ['page_limit' => $options['page_limit']])
            ) : '');

        $varNames = array_merge(
            ['multiple', 'placeholder', 'primary_key', 'autostart', 'query_parameters', 'width', 'render_html', 'class_type'],
            array_keys($this->config)
        );

        foreach (array_unique($varNames) as $varName) {
            $view->vars[$varName] = $options[$varName] ?? ($this->config[$varName] ?? null);
        }

        if (!empty($options['req_params']) && \is_array($options['req_params'])) {
            $accessor = PropertyAccess::createPropertyAccessor();
            $reqParams = [];
            foreach ($options['req_params'] as $key => $reqParam) {
                try {
                    $reqParams[$key] = $accessor->getValue($view, $reqParam . '.vars[full_name]');
                } catch (\Throwable) {
                    $reqParams[$key] = $reqParam;
                }
            }
            $view->vars['attr']['data-req_params'] = json_encode($reqParams, JSON_THROW_ON_ERROR);
        }

        // Tags options
        $view->vars['allow_add'] = array_merge(
            $this->config['allow_add'] ?? [],
            $options['allow_add'] ?? []
        );

        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'table_name' => $this->config['table_name'] ?? null,
            'class' => null,
            'data_class' => null,
            'primary_key' => $this->config['primary_key'] ?? 'id',
            'remote_path' => null,
            'remote_route' => null,
            'remote_params' => [],
            'multiple' => false,
            'compound' => false,
            'minimum_input_length' => $this->config['minimum_input_length'] ?? 1,
            'page_limit' => $this->config['page_limit'] ?? 10,
            'scroll' => $this->config['scroll'] ?? false,
            'allow_clear' => $this->config['allow_clear'] ?? false,
            'allow_add' => [
                'enabled' => $this->config['allow_add']['enabled'] ?? false,
                'new_tag_text' => $this->config['allow_add']['new_tag_text'] ?? ' (NEW)',
                'new_tag_prefix' => $this->config['allow_add']['new_tag_prefix'] ?? '__',
                'tag_separators' => $this->config['allow_add']['tag_separators'] ?? '[",", " "]',
            ],
            'delay' => $this->config['delay'] ?? 250,
            'text_property' => $this->config['text_property'] ?? null,
            'placeholder' => false,
            'language' => $this->config['language'] ?? 'en',
            'theme' => $this->config['theme'] ?? 'default',
            'required' => false,
            'cache' => $this->config['cache'] ?? true,
            'cache_timeout' => $this->config['cache_timeout'] ?? 60000,
            'transformer' => null,
            'autostart' => true,
            'width' => $this->config['width'] ?? null,
            'req_params' => [],
            'property' => null,
            'callback' => null,
            'class_type' => null,
            'query_parameters' => [],
            'render_html' => $this->config['render_html'] ?? false,
        ]);

        $resolver->setAllowedTypes('table_name', ['null', 'string']);
        $resolver->setAllowedTypes('primary_key', 'string');
        $resolver->setAllowedTypes('remote_path', ['null', 'string']);
        $resolver->setAllowedTypes('remote_route', ['null', 'string']);
        $resolver->setAllowedTypes('remote_params', 'array');
        $resolver->setAllowedTypes('multiple', 'bool');
        $resolver->setAllowedTypes('compound', 'bool');
        $resolver->setAllowedTypes('minimum_input_length', 'int');
        $resolver->setAllowedTypes('page_limit', 'int');
        $resolver->setAllowedTypes('scroll', 'bool');
        $resolver->setAllowedTypes('allow_clear', 'bool');
        $resolver->setAllowedTypes('allow_add', 'array');
        $resolver->setAllowedTypes('delay', 'int');
        $resolver->setAllowedTypes('text_property', ['null', 'string']);
        $resolver->setAllowedTypes('placeholder', ['null', 'string', 'bool']);
        $resolver->setAllowedTypes('language', 'string');
        $resolver->setAllowedTypes('theme', 'string');
        $resolver->setAllowedTypes('cache', 'bool');
        $resolver->setAllowedTypes('cache_timeout', 'int');
        $resolver->setAllowedTypes('transformer', ['null', 'string', 'object']);
        $resolver->setAllowedTypes('autostart', 'bool');
        $resolver->setAllowedTypes('width', ['null', 'string']);
        $resolver->setAllowedTypes('req_params', 'array');
        $resolver->setAllowedTypes('property', ['null', 'string', 'array']);
        $resolver->setAllowedTypes('callback', ['null', 'callable']);
        $resolver->setAllowedTypes('class_type', ['null', 'string']);
        $resolver->setAllowedTypes('query_parameters', 'array');
        $resolver->setAllowedTypes('render_html', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'saifulferoz_select2table';
    }
}
