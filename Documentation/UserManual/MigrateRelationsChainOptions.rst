.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.  Check: ÄÖÜäöüß

.. include:: ../Includes.txt

Chain options for use with migraterelations command
===================================================

Why is this needed, what does it do?
------------------------------------

Unfortunately, the way image captions, title attributes and alt texts are
rendered on frontend varied wildly with DAM and thus depends on the exact
setup of each individual TYPO3 installation. Basically, you had two options
where to enter data with DAM; either in the content element displaying your
image or on central DAM metadata. The latter option allowed you to change
e.g. the image caption right on the file and it would have updated on all
content elements referencing that image upon clearing the cache. However,
whether this was possible or not depended on your template setup, specifically
``tt_content.image.20``, so your website may or may not have used either central
meta data or texts entered on content elements. If you didn't change image
rendering via your own TypoScript code, the way images were displayed will
mainly depend on whether you added the static include for dam_ttcontent or if
you omitted it.

With FAL (by default) you will get a clean simple logic: If your content element
defines its own texts for caption/description, title or alt text that "override
value" will be used. Otherwise, central metadata will be used. If don't want to
show either, you can check the "override" option for each text field on your
content element and simply leave the text box empty.

When running ``migraterelations`` command, all image texts entered on content
elements will be migrated to the new "override" fields. As those override fields
will determine what's actually rendered on frontend, you have to provide the
priority of fields to be used for your image texts and whether you want FAL
metadata copied (frozen at the time of migration), be used centrally (disabling
"override" option) or not used at all (enabling "override" option with an empty
text field). As this defines how fields are being linked together, we call this
a chain. A different chain can be entered for caption, title and alt text. If
you don't provide the correct chains, your images will still be migrated but
they may show up with wrong or missing captions, titles and alt texts.


Special considerations
----------------------

With the introduction of FAL, TYPO3 renamed the caption field to description.
DAM provided both a caption and a description field. When migrating from DAM to
FAL using this extension, the DAM description field will be mapped to FAL
description. DAM caption will be mapped to ``filemetadata``'s additional
caption field in FAL - which will not be used for frontend rendering by default,
so you may want to check and maybe modify ``tt_content.image`` to use the
correct data when using FAL metadata fallback (``default`` chain option below).

If you have used multiple templates for the same TYPO3 installation or switched
``tt_content.image`` rendering depending on conditions - you would have to find
a compromise as (at the time of writing) you can only migrate with one generic
set of chains and not per page ID.


Chain options
-------------

Options are given by optional parameters ``--image-caption``, ``--image-title``
and ``--image-alt`` when running ``migraterelations``. Each option/field you
entered will be checked for being empty (strings containing white-space are not
considered empty). If it is not empty, it will be used for the "override" text
field on the migrated content element. Multiple options can be given separated
by commas. If an option yielded an empty result, the next option will be
checked. If all checked fields were empty, the last option's value will be used,
allowing you to decide with ``empty`` or ``default`` whether to use central FAL
metadata.

Fields from content elements
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``contentTitle``, ``contentAlt`` and ``contentCaption`` can be specified to
use the individual field of the content element a file was previously used in.
Use these options if, using DAM, texts entered on the content element were
printed on frontend.

Fields from central DAM metadata
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``metaTitle``, ``metaAlt``, ``metaDescription`` and ``metaCaption`` can be
specified to use the individual field from central DAM metadata. Use these
options if, using DAM, texts entered on file properties were printed on
frontend.

Note that using these options will statically copy the field's value to the
"override" fields of your content element, thus freezing it to what your metadata
contained while running the migration - they will not be updated automatically
when editing the central FAL metadata!

Fallback options (use at end of chain)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When no fields matched, you may want to specify whether central FAL metadata
shall be used (now & in future) or if you want to override FAL metadata with an
empty string instead, so no central metadata will be used.

Adding ``empty`` to the end of your chain allows you to disable fallback to FAL
metadata by "checking the override option while leaving the text field empty" on
your content elements.

Adding ``default`` instead will cause that "override" checkbox to be left
unchecked. What's actually shown for your content elements is being determined
by ``tt_content.image``. By default, your images will use central FAL metadata
in that case, allowing central updates to take an immediate effect on all
content elements upon clearing the cache (as was a common use-case with DAM).


Common setups and matching chains
---------------------------------

Read below orders as: "if 1. is empty, use 2."

All options given here are derived from observed behaviour and may not match
your exact TYPO3 installation. Please always verify the migration result.
We recommend to create a backup just before performing DAM migration to
be able to quickly reset and retry migration if you notice any errors.

with dam_ttcontent static include and styles.content.imgtext.captionEach enabled
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Caption:
  1. DAM Caption
  2. DAM Description
- Title:
  1. DAM Title
- Alternative text:
  1. DAM Alt
  2. Content Alt

Note that content fields were not used for title or caption.

Options to freeze texts at time of migration:

::
    --image-caption metaCaption,metaDescription,empty
    --image-title metaTitle,empty
    --image-alt metaAlt,contentAlt,empty

Options to fall back to FAL metadata instead:

::
    --image-caption metaCaption,default
    --image-title default
    --image-alt metaAlt,contentAlt,default

with dam_ttcontent static include and styles.content.imgtext.captionEach disabled
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Caption:
  1. Content Caption
- Title:
  1. DAM Title
- Alternative text:
  1. DAM Alt
  2. Content Alt

Note that content title field was not used.

Options to freeze texts at time of migration:

::
	--image-caption contentCaption,empty
	--image-title metaTitle,empty
	--image-alt metaAlt,contentAlt,empty

Options to fall back to FAL metadata instead: (note that we still freeze
caption as it may not match the unmigrated website otherwise and also alt text
if available at time of migration as we cannot use content alt text otherwise)

::
	--image-caption contentCaption,empty
	--image-title default
	--image-alt metaAlt,contentAlt,default


TYPO3 4.5 default without dam_ttcontent static include
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Caption:
  1. Content Caption
- Title:
  1. Content Title
- Alternative text:
  1. Content Alt

Note that no DAM metadata was used, so it does not make much sense to fall back
to FAL metadata, you will instead want to set migrated file references to an
empty override field.

Options to freeze texts at time of migration:

::
	--image-caption contentCaption,empty
	--image-title contentTitle,empty
	--image-alt contentAlt,empty

TYPO3 6.2 default (just for comparison)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Caption:
  1. Content Description
  2. FAL Description
- Title:
  1. Content Title
  2. FAL Title
- Alternative text:
  1. Content Alt
  2. FAL Alt


Running migration without options (same as before introduction of configurable chains)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Caption:
  1. DAM Description
- Title:
  1. Content Title
  2. DAM Title
- Alternative text:
  1. DAM Alt

This is mainly provided for backwards-compatibility so default behaviour won't
change compared to previous versions of this extension if called without
specifying chains. If you want this behaviour, you can just omit the parameters
listed below:

::
	--image-caption metaDescription,default
	--image-title contentCaption,metaTitle,empty
	--image-alt metaAlt,empty


Examples
--------

Assuming default ``tt_content.image`` setup from CSS Styled Content static includes:

``php typo3/cli_dispatch.phpsh extbase dammigration:migraterelations --image-caption metaCaption,default --image-title default --image-alt metaAlt,contentAlt,default``
  Image caption (new field name: "description") on content elements will be set to the DAM/FAL "caption" (not "description") field as-is at the time of running the migration. If no caption was available, "override" option will be unchecked, allowing central FAL metadata to be used (updating automatically in the future). The field used for captions from FAL metadata is "description" (not "caption"). Image title will always come from FAL (no override) while the alternative text will be copied (frozen) from DAM/FAL metadata if available at migration. If it's not available, content alt text will be copied instead. And if that's missing, FAL metadata will determine what's shown (updating automatically).

``php typo3/cli_dispatch.phpsh extbase dammigration:migraterelations --image-caption contentCaption,empty --image-title contentTitle,empty --image-alt contentAlt,empty``
  Caption, title and alternative texts are only used from content element texts. If they are unavailable at the time of migration, central FAL metadata will be disabled by an empty "override" text field. This prevents FAL/DAM to have any effect on migrated content element file references.

``php typo3/cli_dispatch.phpsh extbase dammigration:migraterelations --image-caption metaDescription,default --image-title contentCaption,metaTitle,empty -image-alt metaAlt,empty`` (default)
  Caption will be copied from old DAM description field, freezing it to the text at time of migration. If it was missing on migration, central FAL field "description" will be used instead. Title is copied from content element caption if set, else DAM title is being copied (frozen). Empty is just provided for a clean end of chain just in case - as DAM titles were mandatory, you are unlikely to encounter any empty file title. Alternative text is copied (frozen) from DAM/FAL alternative text field.
