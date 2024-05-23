(function ($, Drupal) {

  /**
   * Update FAQ pager.
   *
   * @param page
   * @param total_pages
   */
  $.fn.faqPager = function (page, total_pages) {

    $load_more = $('.c-faq__search-load-more');
    if ($load_more.length > 0) {

      $load_more.attr('data-page', page);
      $load_more.attr('data-total-pages', total_pages);

      if (page <= total_pages && total_pages !== 0) {
        $load_more.removeClass('in-active');
      }
      else {
        $load_more.addClass('in-active');
      }
    }
  };

  /**
   * @type {{attach: attach}}
   */
  Drupal.behaviors.hhi_faq = {
    attach: function (context, drupalSettings) {

      hhi_faq_init(context, drupalSettings);
      hhi_faq_init_toggle(context);
      hhi_faq_search_query_filter(context, drupalSettings);

      /**
       * Initialize FAQ.
       *
       * @param context
       * @param drupalSettings
       */
      function hhi_faq_init(context, drupalSettings) {

        /**
         * FAQ Load More
         */
        $('.c-faq__search-load-more', context).once('hhi_faq').on("click", function (e) {

          e.preventDefault();

          $load_more = $(this);
          if ($load_more.hasClass('in-active')) {
            return false;
          }

          var limit = parseInt($load_more.attr('data-limit'));
          var page = parseInt($load_more.attr('data-page'));
          var total_pages = parseInt($load_more.attr('data-total-pages'));

          $.ajax({
            url: '/ajax/faqs/' + page + '/' + limit,
            method: 'GET',
            dataType: "json",
            success: function (response) {
              if (response.faqs) {

                // Update total
                $('.c-faq__search-results-total', context).html(response.total);

                // Update faqs
                $('.c-faq__search-results-ajax', context).append(response.faqs);

                // Check to see if current page meets or exceeds total pages
                if (page >= total_pages) {

                  // Hide pager
                  $load_more.addClass('in-active');
                }
                else {

                  // Update pager
                  page = page + 1;
                  $load_more.attr('data-page', parseInt(page));
                }

                // Re-bind
                air_north_faq_init_toggle(context);
              }
              else {

                // Hide pager
                $load_more.addClass('in-active');
              }
            }
          });
        });

        /**
         * FAQ Category Click Event
         */
        $('.c-faq-search__category', context).once('hhi_faq').on("click", function (e) {

          e.preventDefault();

          var category_id = parseInt($(this).attr('data-tid'));
          var limit = parseInt(drupalSettings.hhi_faq.limit);
          var page = parseInt(drupalSettings.hhi_faq.page);

          $load_more = $('.c-faq__search-load-more');

          $('.c-faq-search__category').removeClass('is-active');
          $(this).addClass('is-active');

          $.ajax({
            url: '/ajax/faqs/' + page + '/' + limit + '/' + category_id,
            method: 'GET',
            dataType: "json",
            success: function (response) {
              if (response.faqs) {

                // Update total
                $('.c-faq__search-results-total', context).html(response.total);

                // Update faqs
                $('.c-faq__search-results-ajax', context).html(response.faqs);

                // Update pager
                if (response.next_page <= response.total_pages) {
                  $load_more.attr('data-total-pages', response.total_pages);
                  $load_more.attr('data-page', response.next_page);
                  $load_more.removeClass('in-active');
                }
                else {
                  // Hide pager
                  $load_more.addClass('in-active')
                }

                // Re-bind
                hhi_faq_init_toggle(context);
              }
              else {
                // Hide pager
                $load_more.addClass('in-active');
              }
            }
          });
        });
      }

      /**
       * Initialize FAQ Toggle(s).
       *
       * @param context
       */
      function hhi_faq_init_toggle(context) {

        /**
         * FAQ Question Toggle Click Event
         */
        $('.c-faq__question-toggle', context).once('hhi_faq').on("click", function (e) {

          e.preventDefault();

          $faq_question = $(this).parent();
          $faq_question.toggleClass('is-open');

          $faq = $(this).closest('.c-faq');
          $('.c-faq__answer', $faq).toggleClass('is-open');
        });
      }

      /**
       * Trigger specific ajax event on query filter change when enter key is
       * pressed.
       *
       * @param context
       */
      function hhi_faq_search_query_filter(context) {

        $('.c-faq-search-form__query', context).once('hhi_faq').on("keydown", function (e) {
          if (e.keyCode === 13) {
            if (drupalSettings.hhi_faq.is_ajax) {
              $(this).trigger("faq_search_query_change");
            }
            else {
              $('.c-faq-search-form').trigger('submit');
            }
            return false;
          }
        });
      }
    }
  };
}(jQuery, Drupal));