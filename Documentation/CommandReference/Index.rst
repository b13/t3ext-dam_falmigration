.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.  Check: ÄÖÜäöüß

.. include:: ../Includes.txt

.. _Command Reference:

Command Reference
=================

.. note:

  This reference uses ``./typo3/cli_dispatch.php extbase`` as the command to
  invoke.

The commands in this reference are shown with their full command identifiers.
On your system you can use shorter identifiers, whose availability depends
on the commands available in total (to avoid overlap the shortest possible
identifier is determined during runtime).

To see the shortest possible identifiers on your system as well as further
commands that may be available, use::

  ./typo3/cli_dispatch.php extbase help

.. note::
  Some commands accept parameters. See './typo3/cli_dispatch.phpsh extbase help <command identifier>' for more information about a specific command.

The following reference was automatically generated from code on 24-12-14

.. contents:: Available Migration Commands
  :local:
  :depth: 1
  :backlinks: top




dam_falmigration:dammigration:cleanupduplicatefalcollectionreferences
*********************************************************************

**Cleanup duplicate FAL collection references**

Checks if there are multiple entries in sys_file_reference that contain the same uid_local and uid_foreign with sys_file_collection references and removes the duplicates
NOTE: this command is usually *NOT* necessary, but only if something
went wrong







dam_falmigration:dammigration:migratecategoryrelations
******************************************************

**Migrate Relations to DAM Categories**

It is highly recommended to update the reference index afterwards.



Options
^^^^^^^

``--record-limit``
  The amount of records to process in a single run. You can set this value if you have memory constraints.





dam_falmigration:dammigration:migratedamcategories
**************************************************

**Migrate DAM categories to FAL categories**





Options
^^^^^^^

``--initial-parent-uid``
  The id of a sys_category record to use as the root category.
``--storage-pid``
  Page id to store created categories on.





dam_falmigration:dammigration:migratedamcategoriestofalcollections
******************************************************************

**migrate all DAM categories to sys_file_collection records,**

while also migrating the references if they don't exist yet
as a pre-requisite, there needs to be sys_file records that
have been migrated from DAM



Options
^^^^^^^

``--file-collection-storage-pid``
  The page id on which to store the collections
``--migrate-references``
  Besides migrating the collections, the references are migrated as well. Default: TRUE





dam_falmigration:dammigration:migratedamfrontendplugins
*******************************************************

**Migrate dam frontend plugins**

Migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection. Usually used in conjunction with or after migrateDamCategoriesToFalCollectionsCommand().







dam_falmigration:dammigration:migratedammetadata
************************************************

**Migrates DAM metadata to FAL metadata.**

Searches for all migrated sys_file records that don't have any titles yet.







dam_falmigration:dammigration:migratedamrecords
***********************************************

**Migrates all DAM records to FAL.**

A database field "_migrateddamuid" connects each FAL record to the original DAM record.



Options
^^^^^^^

``--storage-uid``
  The UID of the storage (default: 1 Do not modify if you are unsure.)
``--record-limit``
  The amount of records to process in a single run. You can set this value if you have memory constraints.





dam_falmigration:dammigration:migratedamttnews
**********************************************

**Migrates tt_news records enriched with DAM fields to FAL.**

It is highly recommended to update the ref index afterwards.



Options
^^^^^^^

``--storage-uid``
  The UID of the storage (default: 1 Do not modify if you are unsure.)





dam_falmigration:dammigration:migratemediatagsinrte
***************************************************

**Migrate RTE media tags**

Migrates the ``<media DAM_UID target title>Linktext</media>`` to ``<link file:29643 - download>Linktext</link>``



Options
^^^^^^^

``--table``
  The table to work on. Default: `tt_content`.
``--field``
  The field to work on. Default: `bodytext`.





dam_falmigration:dammigration:migratelinks
******************************************

**Migrate media:xxx style file references in link fields to file:xxx.**

If optional table & field name is omitted, migration will be performed on ``tt_content.header_link`` and ``tt_content.image_link``. Should be run before ``migrateRelations`` as it transfers ``image_link`` contents to FAL as-is.



Options
^^^^^^^

``--table``
  The table to work on. Default: `tt_content`.
``--field``
  The field to work on. Default if table name is omitted: `header_link` and `image_link`.





dam_falmigration:dammigration:migraterelations
**********************************************

**Migrate relations to DAM records**

Migrate relations to dam records that dam_ttcontent and dam_uploads introduced.

The way image captions, title and alt attributes apply varies wildly across
TYPO3 installations, mainly depending on whether you used the static
include that came with dam_ttcontent and how you configured it. Please
read the `documentation of chain options`_ for more information. To support all
configurations, you can specify a chain for each image caption, title and
alt text which defines the priority of each field. Each chain consists
of one or more of the following options, separated by commas, earliest
non-empty field takes precedence over later ones:

+---------------------+-------------------------------------------------------+
| ``contentTitle``    | title line from content element                       |
+---------------------+-------------------------------------------------------+
| ``contentAlt``      | alt text line from content element                    |
+---------------------+-------------------------------------------------------+
| ``contentCaption``  | caption text line from content element                |
+---------------------+-------------------------------------------------------+
| ``metaTitle``       | title from DAM meta data                              |
+---------------------+-------------------------------------------------------+
| ``metaAlt``         | alt text from DAM meta data                           |
+---------------------+-------------------------------------------------------+
| ``metaCaption``     | caption text from DAM meta data                       |
+---------------------+-------------------------------------------------------+
| ``metaDescription`` | description from DAM meta data                        |
+---------------------+-------------------------------------------------------+
| ``empty``           | ends chain with an empty string if nothing applied    |
|                     | (overriding FAL metadata with no output)              |
+---------------------+-------------------------------------------------------+
| ``default``         | ends chain without overriding FAL metadata if nothing |
|                     | applied (using central FAL metadata without copy)     |
+---------------------+-------------------------------------------------------+

meta options cause DAM/FAL meta data to be copied to the content element,
so they override values entered later on central FAL record. Thus they
freeze input to the state at the time of migration, so later edits of
central metadata won't have any effect on migrated content element
references. Omit meta options and instead add default to the end of your
chain if you want FAL edits to have an effect on migrated content.

It is highly recommended to update the ref index afterwards.



Options
^^^^^^^

``--tablename``
  The tablename to migrate relations for
``--image-caption``
  Chain of fields to determine image captions. (Default: `metaDescription,default`)
``--image-title``
  Chain of fields to determine image title. (Default: `contentCaption,metaTitle,empty`)
``--image-alt``
  Chain of fields to determine image alt texts. (Default: `metaAlt,empty`)
``--uploads-layout``
  The layout ID to set on migrated CType uploads ("file links") content elements. 1 shows file type icons (like dam_filelinks did), 2 shows a thumbnail preview instead, 0 shows nothing but link & caption. Set to 'null' if no action should be taken. Default: 1


_documentation of chain options: ../UserManual/MigrateRelationsChainOptions.rst



dam_falmigration:dammigration:migrateselections
***********************************************

**Migrate DAM selections**

Migrates all available DAM Selections in sys_file_collections (only folder based selections for now).

It is highly recommended to update the ref index afterwards.







dam_falmigration:dammigration:updatereferenceindex
**************************************************

**Updates the reference index**









dam_falmigration:dammigration:upgradestorageindex
*************************************************

**Upgrade the storage index.**





Options
^^^^^^^

``--storage-uid``
  The UID of the storage (default: 1 Do not modify if you are unsure.)
``--record-limit``
  The amount of records to process in a single run. You can set this value if you have memory constraints.





