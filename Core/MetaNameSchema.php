<?php
/**
 * NovaeZSEOBundle MetaNameSchema
 *
 * @package   Novactive\Bundle\eZSEOBundle
 * @author    Novactive <novaseobundle@novactive.com>
 * @copyright 2015 Novactive
 * @license   https://github.com/Novactive/NovaeZSEOBundle/blob/master/LICENSE MIT Licence
 */

namespace Novactive\Bundle\eZSEOBundle\Core;

use eZ\Publish\Core\Repository\NameSchemaService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\Core\FieldType\XmlText\Converter\Html5 as Html5Converter;

/**
 * Class MetaNameSchema
 */
class MetaNameSchema extends NameSchemaService
{

    /**
     * Prioritized languages
     *
     * @var array
     */
    protected $languages;

    /**
     * Html5 converter
     *
     * @var Html5Converter
     */
    protected $_html5Converter;

    /**
     * Set prioritized languages
     *
     * @param array $languages
     */
    public function setLanguages( array $languages = null )
    {
        $this->languages = $languages;
    }

    /**
     * Set HTML Converter
     *
     * @param Html5Converter $converter
     */
    public function setHtml5Converter( Html5Converter $converter )
    {
        $this->_html5Converter = $converter;
    }

    /**
     * Resolve a Meta Value
     *
     * @param Meta        $meta
     * @param Content     $content
     * @param ContentType $contentType
     *
     * @return boolean
     */
    public function resolveMeta( Meta $meta, Content $content, ContentType $contentType = null )
    {
        if ( $contentType === null )
        {
            $contentType = $this->repository->getContentTypeService()->loadContentType(
                $content->contentInfo->contentTypeId
            );
        }

        $resolveMultilingue = $this->resolve(
            $meta->getContent(),
            $contentType,
            $content->fields,
            $content->versionInfo->languageCodes
        );
        // we don't fallback on the other languages... it would be very bad for SEO to mix the languages
        if ( ( array_key_exists( $this->languages[0], $resolveMultilingue ) ) &&
             ( $resolveMultilingue[$this->languages[0]] != '' )
        )
        {
            $meta->setContent( $resolveMultilingue[$this->languages[0]] );
            return true;
        }
        $meta->setContent( "" );
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldTitles( array $schemaIdentifiers, ContentType $contentType, array $fieldMap, $languageCode )
    {
        $fieldTitles = array();

        foreach ( $schemaIdentifiers as $fieldDefinitionIdentifier )
        {
            if ( isset( $fieldMap[$fieldDefinitionIdentifier][$languageCode] ) )
            {
                $fieldDefinition = $contentType->getFieldDefinition( $fieldDefinitionIdentifier );
                $fieldType       = $this->repository->getFieldTypeService()->getFieldType(
                    $fieldDefinition->fieldTypeIdentifier
                );
                // eZ XML Text
                if ( $fieldMap[$fieldDefinitionIdentifier][$languageCode] instanceof
                     \eZ\Publish\Core\FieldType\XmlText\Value
                )
                {
                    $fieldTitles[$fieldDefinitionIdentifier] = trim(
                        strip_tags(
                            $this->_html5Converter->convert( $fieldMap[$fieldDefinitionIdentifier][$languageCode]->xml )
                        )
                    );
                    continue;
                }

                //eZ Object Relation
                if ( $fieldMap[$fieldDefinitionIdentifier][$languageCode] instanceof
                     \eZ\Publish\Core\FieldType\Relation\Value
                )
                {
                    if ( $fieldMap[$fieldDefinitionIdentifier][$languageCode]->destinationContentId )
                    {
                        $relatedContent = $this->repository->getContentService()->loadContent(
                            $fieldMap[$fieldDefinitionIdentifier][$languageCode]->destinationContentId
                        );
                        // check only Image here
                        // @todo: we can do better
                        if ( $fieldImageValue = $relatedContent->getFieldValue( 'image' ) )
                        {
                            $fieldTitles[$fieldDefinitionIdentifier] = $fieldImageValue->uri;
                            continue;
                        }
                    }
                    $fieldTitles[$fieldDefinitionIdentifier] = '';
                    continue;
                }

                // eZ Image
                if ( $fieldMap[$fieldDefinitionIdentifier][$languageCode] instanceof
                     \eZ\Publish\Core\FieldType\Image\Value
                )
                {
                    if ( $fieldMap[$fieldDefinitionIdentifier][$languageCode]->uri )
                    {
                        $fieldTitles[$fieldDefinitionIdentifier] = $fieldMap[$fieldDefinitionIdentifier][$languageCode]->uri;
                        continue;
                    }
                }

                $fieldTitles[$fieldDefinitionIdentifier] = $fieldType->getName(
                    $fieldMap[$fieldDefinitionIdentifier][$languageCode]
                );
            }
        }
        return $fieldTitles;
    }
}
