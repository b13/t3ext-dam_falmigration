t3ext-dam_falmigration
======================

TYPO3 Extension: Migrate DAM Records and Relations to TYPO3 6.2s File Abstraction Layer (FAL)

This extension only works with TYPO3 6.2 and higher.

Introduction
============

First of all: Remove all scheduler tasks of this extension you have defined in scheduler module
--> That's because of a completely rewritten code. Scheduler serializes the task-classes and this information
has to be removed

Tasks
=======

For now only two tasks works:

Migrate DAM Records to FAL Records
----------------------------------

This task searches for dam records (tx_dam) which were not migrated already in FAL. The extension adds a new
col to sys_file called "_migrateddamuid" to identify migrated dam records.

TYPO3 6.2 brings a new table sys_file_metadata. This task will port following cols to this new table::

 title, hpixels, vpixels, description, alt_text

If you have activated the new sys extension "filemetadata" this task adds some more fields to this table::

 creator, keywords, caption, language, pages, publisher, loc_country, loc_city

**no files will be moved or copied**

Migrate DAM Relations to FAL Relations
--------------------------------------

Before executing this task you have to execute "Migrate DAM Records to FAL Records" first, because this task
needs sys_file records with a given _migrateddamuid set.

If there a no already migrated records found, the task will break migration of relations.

This task get all records from tx_dam_mm_ref which have an already migrated record in sys_file. For each of this
records it collects additional informations and write them into sys_file_reference

**no files will be moved or copied**