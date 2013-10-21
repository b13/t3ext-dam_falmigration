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

For now only one tasks works:

DAM-FAL Migration: Migrate DAM Records to FAL Records (dam_falmigration)
------------------------------------------------------------------------

This task searches for dam records (tx_dam) which were not migrated already in FAL. The extension adds a new
col to sys_file called "_migrateddamuid" to identify migrated dam records.

TYPO3 6.2 brings a new table sys_file_metadata. Our extension will port following cols to this new table::

 title, hpixels, vpixels, description, alt_text

If you have activated the new sys extension filemetadata this tasks adds some more fields to this table::

 creator, keywords, caption, language, pages, publisher, loc_country, loc_city