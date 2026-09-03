<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter\referenceData;

use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\plugins\importexport\native\filter\NativeImportFilter;
use PKP\submission\Genre;

class NativeXmlGenreFilter extends NativeImportFilter
{
    use NativeXmlReferenceDataFilterTrait;

    public function getPluralElementName()
    {
        return 'genres';
    }

    public function getSingularElementName()
    {
        return 'genre';
    }

    public function handleElement($node)
    {
        $context = $this->getDeployment()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $map = $this->getDeployment()->getReferenceMap('genre');
        $sourceReference = $this->sourceReference($node, $map);
        $category = $this->integerAttribute($node, 'category');
        if (!in_array($category, [
            Genre::GENRE_CATEGORY_DOCUMENT,
            Genre::GENRE_CATEGORY_ARTWORK,
            Genre::GENRE_CATEGORY_SUPPLEMENTARY,
        ], true)) {
            throw new InvalidArgumentException('Invalid genre category');
        }
        $genre = $genreDao->newDataObject();
        $genre->setContextId($context->getId());
        $genre->setKey($this->requiredAttribute($node, 'key'));
        $genre->setCategory($category);
        $genre->setDependent($this->booleanAttribute($node, 'dependent'));
        $genre->setSupplementary($this->booleanAttribute($node, 'supplementary'));
        $genre->setRequired($this->booleanAttribute($node, 'required'));
        $genre->setSequence($this->floatAttribute($node, 'sequence'));
        $genre->setEnabled($this->booleanAttribute($node, 'enabled'));
        $this->applyLocalized($node, 'name', [$genre, 'setName'], true);
        $id = $genreDao->insertObject($genre);
        $this->getDeployment()->mapReference('genre', $sourceReference, $id);
        return $genre;
    }
}
