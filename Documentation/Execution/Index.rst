.. ==================================================
.. FOR YOUR INFORMATION 
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.  Check: ÄÖÜäöüß

.. include:: ../Includes.txt

=======================
Executing the migration
=======================

Step 1: Connecting DAM and FAL records
======================================

dammigration:connectdamrecordswithsysfile
goes through all DAM files and checks if they have a counterpart in the sys_file

Step 2: Migrate metadata
========================
dammigration:migratedammetadata
migrates DAM metadata to FAL metadata

Step 3: Migrate RTE media tags
==============================
dammigration:migratemediatagsinrte
migrates the <media DAM_UID target title>Linktext</media>

Step 4: Migrate categories to collections
=========================================
dammigration:migratedamcategoriestofalcollections
migrate all DAM categories to sys_file_collection records,

Step 5: Migrate EXT: dam_frontend
=================================
dammigration:migratedamfrontendplugins
migrate all damfrontend_pi1 plugins to tt_content.uploads with file_collection

Step 6: Remove duplicate collection references
==============================================
dammigration:cleanupduplicatefalcollectionreferences
checks if there are multiple entries in sys_file_reference that contain

Step 7: Migrate content relations
=================================
dammigration:migraterelations

Step 8: Update the reference index
==================================
dammigration:updatereferenceindex
updates the reference index

Next pages:

.. toctree::
   :maxdepth: 5
   :glob:
   :titlesonly:

   *