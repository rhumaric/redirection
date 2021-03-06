/**
 * Internal dependencies
 */

import { STATUS_IN_PROGRESS } from './type';

export function getInitialSettings() {
	return {
		loadStatus: STATUS_IN_PROGRESS,
		saveStatus: false,
		error: false,
		settings: {},
	};
}
