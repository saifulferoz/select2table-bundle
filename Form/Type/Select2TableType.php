<?php

namespace Feroz\Select2TableBundle\Form\Type;

use Doctrine\DBAL\Connection;
use Feroz\Select2TableBundle\Form\DataTransformer\EntitiesToPropertyTransformer;
use Feroz\Select2TableBundle\Form\DataTransformer\EntityToPropertyTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 *
 * Class Select2TableType
 * @package Feroz\Select2TableBundle\Form\Type
 */
class Select2TableType extends AbstractType
{
    public function __construct(
        private Connection $connection,
        private RouterInterface $router,
        private array $config = []
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // add custom data transformer
        if ($options['transformer']) {
            if (!is_string($options['transformer'])) {
                throw new \Exception('The option transformer must be a string');
            }
            if (!class_exists($options['transformer'])) {
                throw new \Exception('Unable to load class: '.$options['transformer']);
            }

            $transformer = new $options['transformer'](
                $this->connection,
                $options['table_name'],
                $options['text_property'],
                $options['primary_key']
            );

            if (!$transformer instanceof DataTransformerInterface) {
                throw new \Exception(
                    sprintf(
                        'The custom transformer %s must implement "Symfony\Component\Form\DataTransformerInterface"',
                        get_class($transformer)
                    )
                );
            }
        } else {
            $newTagPrefix = $options['allow_add']['new_tag_prefix'] ?? $this->config['allow_add']['new_tag_prefix'];
            $newTagText = $options['allow_add']['new_tag_text'] ?? $this->config['allow_add']['new_tag_text'];

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
        // make variables available to the view
        $view->vars['remote_path'] = $options['remote_path']
            ?: $this->router->generate(
                $options['remote_route'],
                array_merge($options['remote_params'], ['page_limit' => $options['page_limit']])
            );

        // merge variable names which are only set per instance with those from yml config
        $varNames = array_merge(['multiple', 'placeholder', 'primary_key', 'autostart', 'query_parameters'],
            array_keys($this->config));
        foreach ($varNames as $varName) {
            $view->vars[$varName] = $options[$varName];
        }

        if (isset($options['req_params']) && is_array($options['req_params']) && count($options['req_params']) > 0) {
            $accessor = PropertyAccess::createPropertyAccessor();

            $reqParams = [];
            foreach ($options['req_params'] as $key => $reqParam) {
                $reqParams[$key] = $accessor->getValue($view, $reqParam.'.vars[full_name]');
            }

            $view->vars['attr']['data-req_params'] = json_encode($reqParams);
        }

        //tags options
        $varNames = array_keys($this->config['allow_add']);
        foreach ($varNames as $varName) {
            $view->vars['allow_add'][$varName] = $options['allow_add'][$varName] ?? $this->config['allow_add'][$varName];
        }

        if ($options['multiple']) {
            $view->vars['full_name'] .= '[]';
        }

        $view->vars['class_type'] = $options['class_type'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
                'object_manager' => null,
                'class' => null,
                'data_class' => null,
                'primary_key' => 'id',
                'remote_path' => null,
                'remote_route' => null,
                'remote_params' => [],
                'multiple' => false,
                'compound' => false,
                'minimum_input_length' => $this->config['minimum_input_length'],
                'page_limit' => $this->config['page_limit'],
                'scroll' => $this->config['scroll'],
                'allow_clear' => $this->config['allow_clear'],
                'allow_add' => [
                    'enabled' => $this->config['allow_add']['enabled'],
                    'new_tag_text' => $this->config['allow_add']['new_tag_text'],
                    'new_tag_prefix' => $this->config['allow_add']['new_tag_prefix'],
                    'tag_separators' => $this->config['allow_add']['tag_separators'],
                ],
                'delay' => $this->config['delay'],
                'text_property' => null,
                'placeholder' => false,
                'language' => $this->config['language'],
                'theme' => $this->config['theme'],
                'required' => false,
                'cache' => $this->config['cache'],
                'cache_timeout' => $this->config['cache_timeout'],
                'transformer' => null,
                'autostart' => true,
                'width' => $this->config['width'] ?? null,
                'req_params' => [],
                'property' => null,
                'callback' => null,
                'class_type' => null,
                'query_parameters' => [],
                'render_html' => $this->config['render_html'] ?? false,
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'feroz_select2table';
    }
}