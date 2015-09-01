.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.  Check: ÄÖÜäöüß

.. include:: ../Includes.txt

.. _user-manual:

===========
User Manual
===========

The migration tasks can be long running processes. Therefore they are executed using the command line dispatcher.

You can execute the dispathcer from the root of your website:

.. code-block:: bash

	./typo3/cli_dispatch.phpsh

The available migration tasks can be found under the *extbase* cliKey:

.. code-block:: bash

	./typo3/cli_dispatch.phpsh extbase help

	The following commands are currently available:

	EXTENSION "DAM_FALMIGRATION":
	-------------------------------------------------------------------------------
	  dammigration:migratedamrecordstostorage  Ensures that all DAM files are stored
	                                           in a FAL storage. A new subfolder
	                                           "_migrated/dam" is created and files
	                                           are copied and indexed.
	  dammigration:migratedamrecords           Migrates all DAM records to FAL. A
	                                           DB field "_migrateddamuid" connects
	                                           each FAL record to the original DAM
	                                           record.
	  dammigration:migratedammetadata          Migrates DAM metadata to FAL
	                                           metadata. Searches for all migrated
	                                           sys_file records that do not have any
	                                           titles yet.
	  dammigration:migratemediatagsinrte       Migrates the <media DAM_UID target
	                                           title>Linktext</media> to <link
	                                           file:29643 -
	                                           download>Linktext</link>
	  dammigration:migratedamcategories        Migrate DAM categories to FAL
	                                           categories
	  dammigration:migratedamcategoriestofalcollections migrate all DAM categories to
	                                           sys_file_collection records,
	  dammigration:migratecategoryrelations    Migrate DAM Category Relations
	  dammigration:migratedamfrontendplugins   migrate all damfrontend_pi1 plugins
	                                           to tt_content.uploads with
	                                           file_collection usually used in
	                                           conjunction with / after
	                                           migrateDamCategoriesToFalCollections
	                                           Command()
	  dammigration:cleanupduplicatefalcollectionreferences Checks if there are multiple entries
	                                           in sys_file_reference that contain
	                                           the same uid_local and uid_foreign
	                                           with sys_file_collection references
	                                           and removes the duplicates
	  dammigration:updatereferenceindex        updates the reference index
	  dammigration:migratelinks                migrates media: to file: links
	                                           (must be run before migraterelations)
	  dammigration:migraterelations            migrate relations to dam records
	                                           that dam_ttcontent and dam_uploads
	                                           introduced
	  dammigration:migrateselections           Migrates all available DAM
	                                           Selections in sys_file_collections
	                                           (only folder based selections for
	                                           now).
	  dammigration:migratedamttnews            Migrates tt_news records enriched
	                                           with DAM fields to FAL.
	  dammigration:upgradestorageindex         Service to Upgrade the storage
	                                           index.

.. note::
	Some commands accept parameters. See './typo3/cli_dispatch.phpsh extbase help <command identifier>' for more information about a specific command.

Please see the :ref:`Command Reference` for an explanation of the commands.

In general you will want to execute the commands 'migratedamrecords' and 'migratedammetadata' first, then migrate any links using 'migratelinks'. After that you may wish to migrate the tx_dam_mm_ref table to sys_file_reference by running the 'migraterelations' command.
