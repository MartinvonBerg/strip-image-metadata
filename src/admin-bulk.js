'use strict';

document.addEventListener('DOMContentLoaded', () => {
	const mediaForm = document.getElementById('posts-filter');

	if (!(mediaForm instanceof HTMLFormElement)) {
		return;
	}

	mediaForm.addEventListener('submit', async (event) => {
		const action = getSelectedBulkAction(mediaForm);

		if (action !== 'wp_strip_image_metadata') {
			return;
		}

		event.preventDefault();
        removeExistingNotice();

		const attachmentIds = getSelectedAttachmentIds(mediaForm);

		if (attachmentIds.length === 0) {
			console.error(
				'Strip Image Metadata: No attachment was selected.'
			);
            /*
			showSelectionNotice(
				'Please select at least one image to process.',
				'error'
			);
            */
			return;
		}

		let successfulAttachments = 0;
		let failedAttachments = 0;
		let processedPaths = 0;
		let failedPaths = 0;

		const errors = [];

		for (const [index, attachmentId] of attachmentIds.entries()) {
			showSelectionNotice(
				`Processing image ${index + 1} of ${attachmentIds.length}, `
				+ `ID ${attachmentId}. Please wait...`,
				'info'
			);

			try {
				const restResponse = await processAttachment(attachmentId);
				const result = restResponse.data;

				processedPaths += getNumericValue(
					result,
					'processed_paths'
				);

				failedPaths += getNumericValue(
					result,
					'failed_paths'
				);

				if (
					restResponse.ok
					&& result.success === true
				) {
					++successfulAttachments;

					console.log(
						'Strip Image Metadata: image processed successfully:',
						result
					);

					unselectAttachment(
						mediaForm,
						attachmentId
					);

					continue;
				}

				++failedAttachments;

				const errorEntry = createResultErrorEntry(
					attachmentId,
					result,
					restResponse.status
				);

				errors.push(errorEntry);

				console.error(
					`Strip Image Metadata: processing of attachment `
					+ `${attachmentId} failed:`,
					result
				);

				/*
				 * HTTP 422 is the regular REST response for an attachment
				 * that was processed but had one or more file errors.
				 */
				if (
					restResponse.status === 422
					&& result.success === false
				) {
					unselectAttachment(
						mediaForm,
						attachmentId
					);
				}
			} catch (error) {
				++failedAttachments;

				const message = error instanceof Error
					? error.message
					: 'An unknown error occurred.';

				errors.push({
					attachmentId,
					filename: '',
					message,
					messages: [],
					httpStatus: null,
				});

				console.error(
					`Strip Image Metadata: request for attachment `
					+ `${attachmentId} failed:`,
					error
				);

				/*
				 * No valid REST response was received. The attachment
				 * remains selected and can be retried.
				 */
			}
		}

		if (errors.length > 0) {
			console.error(
				'Strip Image Metadata: batch errors:',
				errors
			);
		}

        if (failedAttachments === 0) {
	        unselectAllCheckboxes();
        }

		showFinalReport({
			totalAttachments: attachmentIds.length,
			successfulAttachments,
			failedAttachments,
			processedPaths,
			failedPaths,
			errors,
		});
	});
});

/**
 * Determines which WordPress bulk-action field is active.
 *
 * @param {HTMLFormElement} form Media list form.
 *
 * @returns {string}
 */
function getSelectedBulkAction(form) {
	const topAction = form.querySelector(
		'select[name="action"]'
	);

	const bottomAction = form.querySelector(
		'select[name="action2"]'
	);

	if (
		topAction instanceof HTMLSelectElement
		&& topAction.value !== '-1'
	) {
		return topAction.value;
	}

	if (
		bottomAction instanceof HTMLSelectElement
		&& bottomAction.value !== '-1'
	) {
		return bottomAction.value;
	}

	return '';
}

/**
 * Returns the selected attachment IDs.
 *
 * @param {HTMLFormElement} form Media list form.
 *
 * @returns {number[]}
 */
function getSelectedAttachmentIds(form) {
	const checkedItems = form.querySelectorAll(
		'input[name="media[]"]:checked'
	);

	return Array.from(checkedItems)
		.map(
			(checkbox) => Number.parseInt(
				checkbox.value,
				10
			)
		)
		.filter(
			(id) => Number.isInteger(id) && id > 0
		);
}

/**
 * Sends one attachment ID to the REST endpoint.
 *
 * A valid JSON response is returned even when the HTTP status is 422.
 * This preserves the detailed PHP logger messages.
 *
 * @param {number} attachmentId Attachment ID.
 *
 * @returns {Promise<{
 *     ok: boolean,
 *     status: number,
 *     data: object
 * }>}
 */
async function processAttachment(attachmentId) {
	const response = await fetch(
		wpStripImageMetadata.restUrl,
		{
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': wpStripImageMetadata.nonce,
			},
			body: JSON.stringify({
				attachment_id: attachmentId,
			}),
		}
	);

	let responseData;

	try {
		responseData = await response.json();
	} catch {
		throw new Error(
			`The server returned an invalid response. `
			+ `HTTP status: ${response.status}.`
		);
	}

	if (
		responseData === null
		|| typeof responseData !== 'object'
	) {
		throw new Error(
			`The server returned an invalid JSON structure. `
			+ `HTTP status: ${response.status}.`
		);
	}

	return {
		ok: response.ok,
		status: response.status,
		data: responseData,
	};
}

/**
 * Creates an error entry from a REST response.
 *
 * @param {number} attachmentId Requested attachment ID.
 * @param {object} result REST response data.
 * @param {number} httpStatus HTTP response status.
 *
 * @returns {{
 *     attachmentId: number,
 *     filename: string,
 *     message: string,
 *     messages: string[],
 *     httpStatus: number
 * }}
 */
function createResultErrorEntry(
	attachmentId,
	result,
	httpStatus
) {
	return {
		attachmentId: getResultAttachmentId(
			result,
			attachmentId
		),
		filename: getResultFilename(result),
		message: getResultMessage(
			result,
			httpStatus
		),
		messages: getResultMessages(result),
		httpStatus,
	};
}

/**
 * Returns an attachment ID from the REST response.
 *
 * @param {object} result REST response.
 * @param {number} fallbackId Fallback attachment ID.
 *
 * @returns {number}
 */
function getResultAttachmentId(result, fallbackId) {
	if (
		Object.hasOwn(result, 'attachment_id')
		&& Number.isInteger(
			Number(result.attachment_id)
		)
	) {
		return Number(result.attachment_id);
	}

	return fallbackId;
}

/**
 * Returns a numeric REST response property.
 *
 * @param {object} result REST response.
 * @param {string} property Property name.
 *
 * @returns {number}
 */
function getNumericValue(result, property) {
	if (!Object.hasOwn(result, property)) {
		return 0;
	}

	const value = Number(result[property]);

	return Number.isFinite(value)
		? value
		: 0;
}

/**
 * Returns a usable attachment filename or title.
 *
 * @param {object} result REST response.
 *
 * @returns {string}
 */
function getResultFilename(result) {
	if (
		typeof result.filename === 'string'
		&& result.filename.trim() !== ''
	) {
		return result.filename.trim();
	}

	return '';
}

/**
 * Returns the general REST error message.
 *
 * @param {object} result REST response.
 * @param {number} httpStatus HTTP status.
 *
 * @returns {string}
 */
function getResultMessage(result, httpStatus) {
	if (
		typeof result.message === 'string'
		&& result.message.trim() !== ''
	) {
		return result.message.trim();
	}

	return `REST request failed with HTTP status ${httpStatus}.`;
}

/**
 * Returns the detailed PHP logger messages.
 *
 * @param {object} result REST response.
 *
 * @returns {string[]}
 */
function getResultMessages(result) {
	if (!Array.isArray(result.messages)) {
		return [];
	}

	return result.messages
		.filter(
			(message) => (
				typeof message === 'string'
				&& message.trim() !== ''
			)
		)
		.map(
			(message) => message.trim()
		);
}

/**
 * Unselects one attachment in the WordPress media list.
 *
 * @param {HTMLFormElement} form Media list form.
 * @param {number} attachmentId Attachment ID.
 *
 * @returns {void}
 */
function unselectAttachment(form, attachmentId) {
	const checkbox = form.querySelector(
		`input[name="media[]"][value="${attachmentId}"]`
	);

	if (!(checkbox instanceof HTMLInputElement)) {
		return;
	}

	checkbox.checked = false;

	checkbox.dispatchEvent(
		new Event(
			'change',
			{
				bubbles: true,
			}
		)
	);
}

/**
 * Shows a temporary WordPress admin notice.
 *
 * @param {string} message Notice text.
 * @param {'info'|'success'|'warning'|'error'} type Notice type.
 *
 * @returns {void}
 */
function showSelectionNotice(
	message,
	type = 'info'
) {
	removeExistingNotice();

	const notice = document.createElement('div');

	notice.id =
		'wp-strip-image-metadata-bulk-notice';

	notice.className =
		`notice notice-${type}`;

	const paragraph = document.createElement('p');

	paragraph.textContent = message;

	notice.append(paragraph);

	insertNotice(notice);
}

/**
 * Shows the final batch report.
 *
 * @param {object} report Batch report.
 * @param {number} report.totalAttachments Selected attachments.
 * @param {number} report.successfulAttachments Successful attachments.
 * @param {number} report.failedAttachments Failed attachments.
 * @param {number} report.processedPaths Processed files.
 * @param {number} report.failedPaths Failed files.
 * @param {Array<object>} report.errors Error entries.
 *
 * @returns {void}
 */
function showFinalReport(report) {
	removeExistingNotice();

	const notice = document.createElement('div');

	notice.id =
		'wp-strip-image-metadata-bulk-notice';

	notice.className =
		report.failedAttachments === 0
			? 'notice notice-success is-dismissible'
			: 'notice notice-error is-dismissible';

	const heading = document.createElement('p');
	const headingStrong = document.createElement('strong');

	headingStrong.textContent =
		'Strip Image Metadata: processing finished.';

	heading.append(headingStrong);

	const statistics = document.createElement('p');

	statistics.textContent =
		`${report.successfulAttachments} of `
		+ `${report.totalAttachments} images were processed `
		+ `successfully. `
		+ `${report.processedPaths} files were stripped. `
		+ `${report.failedPaths} files failed.`;

	notice.append(
		heading,
		statistics
	);

	if (report.errors.length > 0) {
		const errorHeading = document.createElement('p');
		const errorHeadingStrong =
			document.createElement('strong');

		errorHeadingStrong.textContent =
			'Failed images and error causes:';

		errorHeading.append(errorHeadingStrong);

		const errorList = document.createElement('ol');

		for (const errorEntry of report.errors) {
			errorList.append(
				createErrorListItem(errorEntry)
			);
		}

		notice.append(
			errorHeading,
			errorList
		);
	}

	insertNotice(notice);

    addDismissButton(notice);
}

/**
 * Creates one detailed error item for the final report.
 *
 * @param {object} errorEntry Error data.
 * @param {number} errorEntry.attachmentId Attachment ID.
 * @param {string} errorEntry.filename Filename or title.
 * @param {string} errorEntry.message General message.
 * @param {string[]} errorEntry.messages PHP logger messages.
 * @param {?number} errorEntry.httpStatus HTTP status.
 *
 * @returns {HTMLLIElement}
 */
function createErrorListItem(errorEntry) {
	const listItem = document.createElement('li');

	const title = document.createElement('strong');

	title.textContent = errorEntry.filename !== ''
		? `${errorEntry.filename} — Attachment ID `
			+ `${errorEntry.attachmentId}`
		: `Attachment ID ${errorEntry.attachmentId}`;

	listItem.append(title);

	if (errorEntry.httpStatus !== null) {
		const httpStatus = document.createElement('p');

		httpStatus.textContent =
			`HTTP status: ${errorEntry.httpStatus}`;

		listItem.append(httpStatus);
	}

	const generalMessage = document.createElement('p');

	generalMessage.textContent =
		errorEntry.message;

	listItem.append(generalMessage);

	if (errorEntry.messages.length > 0) {
		const loggerHeading =
			document.createElement('p');

		const loggerHeadingStrong =
			document.createElement('strong');

		loggerHeadingStrong.textContent =
			'Details:';

		loggerHeading.append(
			loggerHeadingStrong
		);

		const loggerList =
			document.createElement('ul');

		for (
			const loggerMessage
			of errorEntry.messages
		) {
			const loggerItem =
				document.createElement('li');

			loggerItem.textContent =
				loggerMessage;

			loggerList.append(loggerItem);
		}

		listItem.append(
			loggerHeading,
			loggerList
		);
	}

	return listItem;
}

/**
 * Removes the current plugin notice.
 *
 * @returns {void}
 */
function removeExistingNotice() {
	const existingNotice =
		document.getElementById(
			'wp-strip-image-metadata-bulk-notice'
		);

	existingNotice?.remove();
}

/**
 * Inserts a notice below the media list heading.
 *
 * @param {HTMLElement} notice Notice element.
 *
 * @returns {void}
 */
function insertNotice(notice) {
	const heading = document.querySelector(
		'.wrap > h1'
	);

	if (heading instanceof HTMLElement) {
		heading.insertAdjacentElement(
			'afterend',
			notice
		);
	}
}

function addDismissButton(notice) {
	const button = document.createElement('button');

	button.type = 'button';
	button.className = 'notice-dismiss';

	const screenReaderText = document.createElement('span');

	screenReaderText.className = 'screen-reader-text';
	screenReaderText.textContent = 'Dismiss this notice.';

	button.append(screenReaderText);

	button.addEventListener('click', () => {
		notice.remove();
	});

	notice.append(button);
}

/**
 * Unselects the WordPress "select all" checkboxes above and below the table.
 *
 * @returns {void}
 */
function unselectAllCheckboxes() {
	const selectAllCheckboxes = document.querySelectorAll(
		'#cb-select-all-1, #cb-select-all-2'
	);

	for (const checkbox of selectAllCheckboxes) {
		if (!(checkbox instanceof HTMLInputElement)) {
			continue;
		}

		checkbox.checked = false;
		checkbox.indeterminate = false;

		checkbox.dispatchEvent(
			new Event('change', {
				bubbles: true,
			})
		);
	}
}