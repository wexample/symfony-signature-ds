# symfony-signature-ds

Version: 3.0.7

Design-system screens for symfony-signature: composing an envelope, signing it — signed in or through a signed link —, following it, verifying a file, and one's own signature

## Table of Contents

- [Installation](#installation)

## Installation

```php
// config/bundles.php
Wexample\SymfonyMedia\WexampleSymfonyMediaBundle::class => ['all' => true],
Wexample\SymfonyMediaDs\WexampleSymfonyMediaDsBundle::class => ['all' => true],
Wexample\SymfonySignature\WexampleSymfonySignatureBundle::class => ['all' => true],
Wexample\SymfonySignatureDs\WexampleSymfonySignatureDsBundle::class => ['all' => true],
```

The screens of symfony-signature, drawn with the design system.

| Route | Path | What |
|---|---|---|
| `signature_index` | `/signatures` | what awaits the account's signature, and what it sent |
| `signature_create` | `/signatures/new` | a new envelope: title, documents picked in the media library, order |
| `signature_envelope` | `/signatures/{id}` | one envelope: state, fingerprint, documents and their SHA-256, signers; while a draft, adding signers and sealing; pending, cancelling; sealed, its verification and its proof |
| `signature_sign` | `/signatures/sign/{signer}` | where a signer reads the documents and signs or declines: signed in, or through the signed link of an outside signer |
| `signature_document` | `/signatures/sign/{signer}/document/{document}` | a document, downloaded — signed for an outside signer |
| `signature_envelope_proof` | `/signatures/{id}/proof` | the proof, as a JSON file |
| `signature_verify` | `/signatures/verify` | a file, looked for by its bytes; open to anyone |
| `signature_personal` | `/signatures/mine` | the image the account signs with by default, picked in its media library |

Seal, cancel and remove a signer are buttons of the envelope's tabs, posted with the application's `submit` form token.

An outside signer has no account: opening its signed link marks the session, and the forms of its page — posted elsewhere, without the link — check that mark. A signer signs with its own signature, or an image uploaded on the page, kept in the media library folder `Signatures`, or no image.

What one sees: an envelope, its sender and its account signers; a signing page, its account signed in or whoever holds the outside signer's link. Anyone else gets a 404.
