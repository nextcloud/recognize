<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\DAV\Connector\Sabre;

use Sabre\DAV\ServerPlugin;

class FilesPlugin extends ServerPlugin {
	// namespace
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';
	public const NS_NEXTCLOUD = 'http://nextcloud.org/ns';
	public const FILEID_PROPERTYNAME = '{http://owncloud.org/ns}id';
	public const INTERNAL_FILEID_PROPERTYNAME = '{http://owncloud.org/ns}fileid';
	public const PERMISSIONS_PROPERTYNAME = '{http://owncloud.org/ns}permissions';
	public const SHARE_PERMISSIONS_PROPERTYNAME = '{http://open-collaboration-services.org/ns}share-permissions';
	public const OCM_SHARE_PERMISSIONS_PROPERTYNAME = '{http://open-cloud-mesh.org/ns}share-permissions';
	public const SHARE_ATTRIBUTES_PROPERTYNAME = '{http://nextcloud.org/ns}share-attributes';
	public const DOWNLOADURL_PROPERTYNAME = '{http://owncloud.org/ns}downloadURL';
	public const DOWNLOADURL_EXPIRATION_PROPERTYNAME = '{http://nextcloud.org/ns}download-url-expiration';
	public const SIZE_PROPERTYNAME = '{http://owncloud.org/ns}size';
	public const GETETAG_PROPERTYNAME = '{DAV:}getetag';
	public const LASTMODIFIED_PROPERTYNAME = '{DAV:}lastmodified';
	public const CREATIONDATE_PROPERTYNAME = '{DAV:}creationdate';
	public const DISPLAYNAME_PROPERTYNAME = '{DAV:}displayname';
	public const OWNER_ID_PROPERTYNAME = '{http://owncloud.org/ns}owner-id';
	public const OWNER_DISPLAY_NAME_PROPERTYNAME = '{http://owncloud.org/ns}owner-display-name';
	public const CHECKSUMS_PROPERTYNAME = '{http://owncloud.org/ns}checksums';
	public const DATA_FINGERPRINT_PROPERTYNAME = '{http://owncloud.org/ns}data-fingerprint';
	public const HAS_PREVIEW_PROPERTYNAME = '{http://nextcloud.org/ns}has-preview';
	public const MOUNT_TYPE_PROPERTYNAME = '{http://nextcloud.org/ns}mount-type';
	public const MOUNT_ROOT_PROPERTYNAME = '{http://nextcloud.org/ns}is-mount-root';
	public const IS_FEDERATED_PROPERTYNAME = '{http://nextcloud.org/ns}is-federated';
	public const METADATA_ETAG_PROPERTYNAME = '{http://nextcloud.org/ns}metadata_etag';
	public const UPLOAD_TIME_PROPERTYNAME = '{http://nextcloud.org/ns}upload_time';
	public const CREATION_TIME_PROPERTYNAME = '{http://nextcloud.org/ns}creation_time';
	public const SHARE_NOTE = '{http://nextcloud.org/ns}note';
	public const SHARE_HIDE_DOWNLOAD_PROPERTYNAME = '{http://nextcloud.org/ns}hide-download';
	public const SUBFOLDER_COUNT_PROPERTYNAME = '{http://nextcloud.org/ns}contained-folder-count';
	public const SUBFILE_COUNT_PROPERTYNAME = '{http://nextcloud.org/ns}contained-file-count';
	public const FILE_METADATA_PREFIX = '{http://nextcloud.org/ns}metadata-';
	public const HIDDEN_PROPERTYNAME = '{http://nextcloud.org/ns}hidden';
}
