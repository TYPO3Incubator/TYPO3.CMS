.. include:: ../../Includes.txt

============================================================
Deprecation: #88850 - ContentObjectRenderer::sendNotifyEmail
============================================================

See :issue:`88850`

Description
===========

The method :php:`ContentObjectRenderer::sendNotifyEmail` which has been used to send mails has been marked as deprecated.


Impact
======

Using this method will trigger a PHP :php:`E_USER_DEPRECATED` error.


Affected Installations
======================

Any 3rd party extension calling :php:`ContentObjectRenderer::sendNotifyEmail` is affected.


Migration
=========

To send a mail, use the :php:`MailMessage`-API

.. code-block:: php

    $email = GeneralUtility::makeInstance(MailMessage::class)
         ->to(new Address('john@domain.tld'), new NamedAddress('john@domain.tld', 'John Doe'))
         ->subject('This is an example email')
         ->text('This is the plain-text variant')
         ->html('<h4>Hello John.</h4><p>Enjoy a HTML-readable email. <marquee>We love TYPO3</marquee>.</p>');

    $email->send();

.. index:: PHP-API, FullyScanned, ext:frontend
