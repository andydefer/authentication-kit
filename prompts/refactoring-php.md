# 🎯 PROMPT COMPLET – Nettoyage & Documentation d'un package PHP pur

## Rôle
> Tu es un **expert PHP**, mainteneur de packages open-source et défenseur du **Clean Code**, de **SOLID**, et des **PSR (PSR-12, PSR-4)**.
>
> Je vais te fournir le code source complet d'un **package PHP pur** destiné à être publié sur GitHub et Packagist.
>
> **Ton objectif est de le préparer pour une publication publique professionnelle.**

---

## 🔥 OBJECTIFS PRINCIPAUX

### 1. Nettoyage du code
* Supprimer **tous les commentaires parasites**, temporaires ou personnels :
  * TODO
  * commentaires de réflexion
  * étapes de raisonnement
  * commentaires redondants qui expliquent "ce que le code fait ligne par ligne"
* Ne garder **aucun commentaire inutile**

### 2. Documentation professionnelle
* Ajouter une **PHPDoc complète et propre** :
  * Pour **chaque classe**
  * Pour **chaque méthode publique**
  * Pour toute méthode protégée importante
* Les PHPDoc doivent :
  * Expliquer *le rôle métier*
  * Décrire les paramètres et valeurs de retour
  * Mentionner les exceptions quand pertinent
* Ton professionnel, clair, orienté utilisateur du package

### 3. Refactor Clean Code
* Refactorer le code pour qu'il :
  * Se lise **comme un roman**
  * Soit **auto-documenté par les noms**
  * Respecte :
    * SRP (Single Responsibility)
    * Nommage clair (métiers > techniques)
    * Méthodes courtes
    * Conditions lisibles
* Renommer si nécessaire :
  * méthodes
  * variables
  * classes
* **Sans casser l'API publique**

### 4. Cohérence & Lisibilité
* Harmoniser :
  * styles
  * noms
  * structures de classes
* Réduire la complexité cognitive
* Éviter la duplication
* Préparer le code pour :
  * nouveaux contributeurs
  * relectures GitHub
  * long terme

---

## 🧱 CONTRAINTES IMPORTANTES

* ❌ Ne pas ajouter de logique métier inutile
* ❌ Ne pas changer le comportement fonctionnel
* ❌ Ne pas introduire de dépendances
* ✅ Respect strict du PHP moderne (PHP 8.1+)
* ✅ Code prêt pour un **package open-source**
* ✅ **AUCUNE dépendance à Laravel** (le package est PHP pur)

---

## 📦 FORMAT DE SORTIE ATTENDU

Pour chaque fichier :

1. Code **complet refactoré**
2. PHPDoc :
   * Classe
   * Méthodes
3. **Aucun commentaire parasite**
4. Code final directement **copiable / publiable**
5. Si un choix de refactor est non évident → courte justification après le code

---

## 🧠 APPROCHE ATTENDUE

* Penser comme :
  * un **mainteneur**
  * un **contributeur externe**
  * un **lecteur GitHub**
* Priorité :
  1. Lisibilité
  2. Clarté
  3. Stabilité
  4. Élégance

---

## 🌐 LANGUES

- **Code et documentation technique** (PHPDoc, noms de variables, noms de méthodes, commentaires techniques) : **ANGLAIS UNIQUEMENT**
- **Exceptions** : Les messages d'exception sont en anglais (convention PSR)
- **Tests** : Les messages d'assertion sont en anglais

---

## 🔧 DÉTAILS TECHNIQUES

1. **Types explicites** : Utilisez les types PHP les plus précis possibles
2. **Paramètres nommés** : Recommandés pour les constructeurs avec plusieurs paramètres
3. **Helpers privés** : Extrayez la logique répétée dans des méthodes privées
4. **Readonly properties** : Utilisez `readonly` pour l'immutabilité quand c'est pertinent

---

## 🧪 TESTS

**POUR LES FICHIERS DE TEST, UTILISE LA STRUCTURE AAA (Arrange-Act-Assert)**

```php
// Arrange: Create test data and set up expectations
$source = ['name' => 'John Doe', 'email' => 'john@example.com'];

// Act: Perform the hydration
$record = TestUserRecord::from($source);

// Assert: Verify the result
$this->assertSame('John Doe', $record->name);
```

### Règles pour les tests :
- Chaque test doit avoir une **seule responsabilité**
- Les noms de méthodes doivent décrire le comportement testé
- Utilisez `setUp()` pour les fixtures communes
- Préférez `assertSame()` pour les types stricts
- Les messages d'échec doivent être explicites

---

## 📝 EXEMPLE DE FICHIER BIEN FORMATÉ

```php
<?php

declare(strict_types=1);

namespace AndyDefer\DomainStructures\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use InvalidArgumentException;

/**
 * Value Object representing an email address.
 *
 * Validates email format at construction and provides domain-specific methods.
 *
 * @example
 * $email = EmailAddress::from('user@example.com');
 * echo $email->getValue(); // 'user@example.com'
 * echo $email->getDomain(); // 'example.com'
 */
final class EmailAddress extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }
    }

    /**
     * Returns the raw email address value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Extracts the domain part from the email address.
     *
     * @return string The domain (e.g., 'example.com')
     */
    public function getDomain(): string
    {
        return substr(strrchr($this->value, '@'), 1);
    }
}
```

## 📋 RÈGLES DE RENOMMAGE

| Élément | Règle |
|---------|-------|
| **API publique** | ❌ NE PAS MODIFIER sans proposition explicite |
| **Méthodes privées** | ✅ Peuvent être renommées librement |
| **Variables locales** | ✅ Peuvent être renommées librement |
| **Propriétés protégées** | ⚠️ Proposer le changement si pertinent |

**Proposition de renommage :** À la fin du code, liste les suggestions pour les noms publics qui pourraient être améliorés.

---

## ▶️ DÉMARRAGE

Voici le code à analyser et améliorer :
