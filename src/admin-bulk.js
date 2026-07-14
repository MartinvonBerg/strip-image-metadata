'use strict';

document.addEventListener('DOMContentLoaded', () => {
	const mediaForm = document.getElementById('posts-filter');

	if (!(mediaForm instanceof HTMLFormElement)) {
		return;
	}

	mediaForm.addEventListener('submit', (event) => {
		const action = getSelectedBulkAction(mediaForm);

		if (action !== 'wp_strip_image_metadata') {
			return;
		}

		event.preventDefault();

		const attachmentIds = getSelectedAttachmentIds(mediaForm);

		console.log(
			'Strip Image Metadata – ausgewählte Attachment-IDs:',
			attachmentIds
		);
		showSelectionNotice(attachmentIds);
	});
});

/**
 * Determines which of the two WordPress bulk-action fields was used.
 *
 * @param {HTMLFormElement} form Media list form.
 * @returns {string}
 */
function getSelectedBulkAction(form) {
	const topAction = form.querySelector('select[name="action"]');
	const bottomAction = form.querySelector('select[name="action2"]');

	if (
		topAction instanceof HTMLSelectElement &&
		topAction.value !== '-1'
	) {
		return topAction.value;
	}

	if (
		bottomAction instanceof HTMLSelectElement &&
		bottomAction.value !== '-1'
	) {
		return bottomAction.value;
	}

	return '';
}

/**
 * Returns the IDs of the selected media entries.
 *
 * @param {HTMLFormElement} form Media list form.
 * @returns {number[]}
 */
function getSelectedAttachmentIds(form) {
	const checkedItems = form.querySelectorAll(
		'input[name="media[]"]:checked'
	);

	return Array.from(checkedItems)
		.map((checkbox) => Number.parseInt(checkbox.value, 10))
		.filter((id) => Number.isInteger(id) && id > 0);
}

/**
 * Shows the selected IDs in a temporary WordPress admin notice.
 *
 * @param {number[]} attachmentIds Selected attachment IDs.
 * @returns {void}
 */
function showSelectionNotice(attachmentIds) {
	const existingNotice = document.getElementById(
		'wp-strip-image-metadata-bulk-notice'
	);

	existingNotice?.remove();

	const notice = document.createElement('div');
	notice.id = 'wp-strip-image-metadata-bulk-notice';
	notice.className = 'notice notice-info';

	const paragraph = document.createElement('p');

	if (attachmentIds.length === 0) {
		paragraph.textContent = 'Es wurden keine Bilder ausgewählt.';
	} else {
		paragraph.textContent =
			`Ausgewählte Attachment-IDs: ${attachmentIds.join(', ')}`;
	}

	notice.append(paragraph);

	const heading = document.querySelector('.wrap > h1');

	if (heading instanceof HTMLElement) {
		heading.insertAdjacentElement('afterend', notice);
	}
}