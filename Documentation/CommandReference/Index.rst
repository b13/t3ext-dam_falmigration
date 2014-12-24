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

.. contents:: Available Migration Tasks
:local:
  :depth: 1
    :backlinks: top




dam_falmigration:dammigration:cleanupduplicatefalcollectionreferences
*********************************************************************

**Checks if there are multiple entries in sys_file_reference that contain the same uid_local and uid_foreign with sys_file_collection references and removes the duplicates**

NOTE: this command is usually *NOT* necessary, but only if something
went wrong







dam_falmigration:dammigration:migratecategoryrelations
******************************************************

**Migrate DAM Category Relations**

it is highly recommended to update the ref index afterwards



Options
^^^^^^^

``--record-limit``
  the amount of records to process in a single run





dam_falmigration:dammigration:migratedamcategories
**************************************************

**Migrate DAM categories to FAL categories**



Arguments
^^^^^^^^^

``--initial-parent-uid``
  Initial parent UID
``--storage-pid``
  Store on PID







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
  whether just the categories should be migrated or the references as well





dam_falmigration:dammigration:migratedamfrontendplugins
*******************************************************

**migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection usually used in conjunction with / after migrateDamCategoriesToFalCollectionsCommand()**









dam_falmigration:dammigration:migratedammetadata
************************************************

**Migrates DAM metadata to FAL metadata. Searches for all migrated sys_file records that don't have any titles yet.**









dam_falmigration:dammigration:migratedamrecords
***********************************************

**Migrates all DAM records to FAL. A DB field &quot;_migrateddamuid&quot; connects each FAL record to the original DAM record.**





Options
^^^^^^^

``--storage-uid``
  the UID of the storage (usually 1, don't modify if you are unsure)
``--record-limit``
  the amount of records to process in a single run





dam_falmigration:dammigration:migratedamttnews
**********************************************

**Migrates tt_news records enriched with DAM fields to FAL.**

It is highly recommended to update the ref index afterwards



Options
^^^^^^^

``--storage-uid``
  the UID of the storage (usually 1, don't modify if you are unsure)





dam_falmigration:dammigration:migratemediatagsinrte
***************************************************

**Migrates the &lt;media DAM_UID target title&gt;Linktext&lt;/media&gt; to &lt;link file:29643 - download&gt;Linktext&lt;/link&gt;**





Options
^^^^^^^

``--table``
  the table to look for
``--field``
  the DB field to look for





dam_falmigration:dammigration:migraterelations
**********************************************

**migrate relations to dam records that dam_ttcontent and dam_uploads introduced**

it is highly recommended to update the ref index afterwards



Options
^^^^^^^

``--tablename``
  The tablename to migrate relations for





dam_falmigration:dammigration:migrateselections
***********************************************

**Migrates all available DAM Selections in sys_file_collections (only folder based selections for now).**

it is highly recommended to update the ref index afterwards







dam_falmigration:dammigration:updatereferenceindex
**************************************************

**updates the reference index**









dam_falmigration:dammigration:upgradestorageindex
*************************************************

**Service to Upgrade the storage index.**





Options
^^^^^^^

``--storage-uid``
  the UID of the storage (usually 1, don't modify if you are unsure)
``--record-limit``
  the amount of records to process in a single run





