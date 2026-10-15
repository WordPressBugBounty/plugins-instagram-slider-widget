jQuery(document).ready(function($) {
    $('.wbfb_masonry_container').each(function() {
        var $container = $(this);

        $container.imagesLoaded(function() {
            $container.masonry({
                gutter: Number($container.data('gutter')) || 0,
                itemSelector: '.wbfb_masonry_post'
            });
        });
    });
});
