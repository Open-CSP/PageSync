<?php
/**
 * Created by  : Wikibase Solutions
 * Project     : PageSync
 * Filename    : PSSlots.php
 * Description :
 * Date        : 4-10-2022
 * Time        : 14:22
 */

namespace PageSync\Core;

use CommentStoreComment;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MWContentSerializationException;
use MWException;
use MWUnknownContentModelException;
use User;
use WikiPage;
use MediaWiki\Revision\SlotRecord;

class PSSlots {

	/**
	 * @param int $id
	 *
	 * @return array|false
	 */
	public static function getSlotNamesForPageAndRevision( int $id ) {
		$page = WikiPage::newFromId( $id );
		if ( $page === false || $page === null ) {
			return false;
		}
		$latest_revision = $page->getRevisionRecord();
		if ( $latest_revision === null ) {
			return false;
		}

		return [
			"slots"           => $latest_revision->getSlotRoles(),
			"latest_revision" => $latest_revision
		];
	}

	/**
	 * @param int $id
	 *
	 * @return array|false
	 * @throws MWException
	 * @throws MWUnknownContentModelException
	 */
	public static function getSlotsContentForPage( int $id ) {
		$slot_result = self::getSlotNamesForPageAndRevision( $id );
		if ( $slot_result === false ) {
			return false;
		}
		$slot_roles      = $slot_result['slots'];
		$latest_revision = $slot_result['latest_revision'];

		$slot_contents = [];

		foreach ( $slot_roles as $slot_role ) {
			if ( strtolower( PSConfig::$config['contentSlotsToBeSynced'] ) !== 'all' ) {
				if ( !array_key_exists(
					$slot_role,
					PSConfig::$config['contentSlotsToBeSynced']
				) ) {
					continue;
				}
			}
			if ( !$latest_revision->hasSlot( $slot_role ) ) {
				continue;
			}

			$content_object = $latest_revision->getContent( $slot_role );

			if ( $content_object === null ) {
				continue;
			}
			$content_handler = MediaWikiServices::getInstance()->getContentHandlerFactory()->getContentHandler(
				$content_object->getModel()
			);

			$contentOfSLot = $content_handler->serializeContent( $content_object );

			if ( empty( $contentOfSLot ) && $slot_role !== 'main' ) {
				continue;
			}

			$slot_contents[$slot_role] = $contentOfSLot;
		}

		return $slot_contents;
	}

	/**
	 * @param User $user
	 * @param WikiPage $wikipage_object
	 * @param array $text
	 * @param string $summary
	 *
	 * @return array
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public static function editSlots(
		User $user,
		WikiPage $wikipage_object,
		array $text,
		string $summary
	) : array {
		$status              = true;
		$errors              = [];
		$title_object        = $wikipage_object->getTitle();
		$page_updater        = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();
		$slot_role_registry  = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		foreach ( $text as $slot_name => $content ) {
			//echo "\nWorking with $slot_name";
			// Make sure the slot we are editing exists
			if ( !$slot_role_registry->isDefinedRole( $slot_name ) ) {
				$status   = false;
				$errors[] = wfMessage(
					"wsslots-apierror-unknownslot",
					$slot_name
				); // TODO: Update message name
				unset( $text[$slot_name] );
				continue;
			}
			if ( $content === "" && $slot_name !== SlotRecord::MAIN ) {
				// Remove the slot if $text is empty and the slot name is not MAIN
				echo "\nSlot $slot_name is empty. Removing..";
				$page_updater->removeSlot( $slot_name );
			} else {
				// Set the content for the slot we want to edit
				if ( $old_revision_record !== null && $old_revision_record->hasSlot( $slot_name ) ) {
					$model_id = $old_revision_record->getSlot( $slot_name )->getContent()->getContentHandler()
													->getModelID();
				} else {
					$model_id = $slot_role_registry->getRoleHandler( $slot_name )->getDefaultModel( $title_object );
				}

				$slot_content = ContentHandler::makeContent(
					$content,
					$title_object,
					$model_id
				);
				$page_updater->setContent(
					$slot_name,
					$slot_content
				);
				if ( $slot_name !== SlotRecord::MAIN ) {
					$page_updater->addTag( 'wsslots-slot-edit' ); // TODO: Update message name
				}
			}
		}

		if ( $old_revision_record === null && ! isset( $text[SlotRecord::MAIN] ) ) {
			// The 'main' content slot MUST be set when creating a new page
			echo "\nWe have no older revision for this page and we do not have a main record.";
			echo " So creating an empty Main.";
			$main_content = ContentHandler::makeContent(
				"",
				$title_object
			);
			$page_updater->setContent(
				SlotRecord::MAIN,
				$main_content
			);
		}

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$page_updater->saveRevision(
			$comment,
			EDIT_INTERNAL
		);

		if ( $status === true ) {
			return [
				"result"  => true,
				"changed" => $page_updater->isUnchanged()
			];
		} else {
			return [
				'result' => false,
				'errors' => $errors
			];
		}
	}

	/**
	 * @param User $user
	 * @param WikiPage $wikipage_object
	 * @param string $text
	 * @param string $slot_name
	 * @param string $summary
	 *
	 * @return array
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public static function editSlot(
		User $user,
		WikiPage $wikipage_object,
		string $text,
		string $slot_name,
		string $summary
	) : array {
		$title_object        = $wikipage_object->getTitle();
		$page_updater        = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();
		$slot_role_registry  = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		// Make sure the slot we are editing exists
		if ( !$slot_role_registry->isDefinedRole( $slot_name ) ) {
			return [
				wfMessage(
					"wsslots-apierror-unknownslot",
					$slot_name
				),
				"unknownslot"
			]; // TODO: Update message name
		}

		if ( $text === "" && $slot_name !== SlotRecord::MAIN ) {
			// Remove the slot if $text is empty and the slot name is not MAIN
			$page_updater->removeSlot( $slot_name );
		} else {
			// Set the content for the slot we want to edit
			if ( $old_revision_record !== null && $old_revision_record->hasSlot( $slot_name ) ) {
				$model_id = $old_revision_record->getSlot( $slot_name )->getContent()->getContentHandler()->getModelID(
				);
			} else {
				$model_id = $slot_role_registry->getRoleHandler( $slot_name )->getDefaultModel( $title_object );
			}

			$slot_content = ContentHandler::makeContent(
				$text,
				$title_object,
				$model_id
			);
			$page_updater->setContent(
				$slot_name,
				$slot_content
			);
		}

		if ( $old_revision_record === null ) {
			// The 'main' content slot MUST be set when creating a new page
			$main_content = ContentHandler::makeContent(
				"",
				$title_object
			);
			$page_updater->setContent(
				SlotRecord::MAIN,
				$main_content
			);
		}

		if ( $slot_name !== SlotRecord::MAIN ) {
			$page_updater->addTag( 'wsslots-slot-edit' ); // TODO: Update message name
		}

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$page_updater->saveRevision(
			$comment,
			EDIT_INTERNAL
		);

		return [
			"result"  => true,
			"changed" => $page_updater->isUnchanged()
		];
	}
}
