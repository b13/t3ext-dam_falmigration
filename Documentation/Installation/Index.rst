.. ==================================================
.. FOR YOUR INFORMATION 
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.  Check: ÄÖÜäöüß

.. include:: ../Includes.txt

============================
Installation and preparation
============================

Install the extension
=====================

The installation process is pretty straightforward.
Download and install the extension via TYPO3s extension manager.
You can get the latest source from https://github.com/b13/t3ext-dam_falmigration

Index your data
===============

To make the migration work, you need to have indexed all data you previously had in DAM with
FAL as well.
In case you have not done this yet, FAL offers a scheduler task to take care of that.

Enable the *scheduler* extension in TYPO3s extension manager if you have not yet done this.
Within *scheduler* create a new task and choose "Update storage index".
Pick "fileadmin" as your root storage.

Set up your command line user
=============================

If you have a backend user called *_cli_lowlevel* you're fine.
If not you need to create a new user with the name **_cli_lowlevel**.
It is important that this user does **not** have admin privileges and does **not** have access to any tables, modules etc.
Also this user should not have any usergroups assigned in order to prevent irritations later.

Despite the fact that this user literally can't do anything you still should pick a strong password.
Even though the user does not ave access to any data or modules, it can still log into TYPO3s backend.

Next pages:

.. toctree::
   :maxdepth: 5
   :glob:
   :titlesonly:
