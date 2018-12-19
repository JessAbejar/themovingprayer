/*global ajaxurl*/
;(function( $ ) {
	$(document).ready(function() {
		// Switch to default dashboard.
		$('.dismiss-memcache-notice').on('click', function(e) {
			let $this = $(this);
			$.ajax(
				$this.data('link')
			)
			.success(function() {
				console.log('test');
				$this.parents('.notice-error').remove()
			})

		})
	})
})( jQuery )
