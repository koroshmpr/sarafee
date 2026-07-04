<?php 
function custom_page_description_accordion_shortcode() {
		$page_description = get_field('content', get_option('page_on_front'));

	if (!$page_description) return '';

	ob_start(); ?>
	<section class="custom-accordion-container" id="customPageAccordion">
		<div class="custom-accordion-body">
			<?php echo $page_description; ?>
		</div>
		<div class="custom-accordion-fade"></div>
		<button class="custom-accordion-toggle" type="button">بیشتر</button>
	</section>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const accordion = document.getElementById('customPageAccordion');
			const toggleBtn = accordion.querySelector('.custom-accordion-toggle');

			toggleBtn.addEventListener('click', function () {
				const expanded = accordion.classList.toggle('expanded');
				toggleBtn.textContent = expanded ? 'بستن' : 'بیشتر';
			});
		});
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode('page_description_accordion', 'custom_page_description_accordion_shortcode');