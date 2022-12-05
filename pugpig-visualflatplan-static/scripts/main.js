(function() {

  /* global Backbone, $, _ */

  // url: 'http://incisive-risk.wpnow.demo.pugpig.com/editionfeed/119/pugpig_atom_contents.manifest',
  // url: 'http://newscientist.wpnow.demo.pugpig.com/editionfeed/17/pugpig_atom_contents.manifest',

// TODO
// pull in title dynamically
// parse should not be in the collection - in the feed?

// get a proper number for the article within the edition - but then update this when the pages get rehsuffled

// add ads in a panel, with ability to pull them into the flatplan

// add analytics views

// section filter obliterates order changes and advert placements



  'use strict';

  var APP = {

    getFlatplan: function( feedURL ){
      this.adverts = new Adverts();
      var loading = document.querySelector('.loading-indicator'),
        // adverts = new Adverts(),
        sidePanelView = new SidePanelView({
          collection: this.adverts
        });

      loading.classList.remove('is-hidden');

      articles.url = feedURL;

      articles.fetch({
        reset: true,
        success: function(){
          loading.classList.add('is-hidden');
          var sectionSettingsView = new SectionSettingsView({
            sections: articles.customSections
          });
          sectionSettingsView.render();
          $('.form-settings__filter-by-section').append (sectionSettingsView.el);
        }
      });

      $('.body-panel').append(this.view.el);

      this.adverts.fetch({
        reset: true,
        success: function(){
          sidePanelView.render();
        }
      });

    },

    initialize: function() {
      var submitFormView = new SubmitFormView({
          el: '.submit-form'
        }),
        settingsFormView = new SettingsFormView(),
        getFeedFormView = new GetFeedFormView({
          el: '.form-get-feed'
        }),
        feedURL;
      this.view = new ArticlesView({
        collection: articles
      });
      articles.comparator = 'index';
      feedURL = Feed.getFeedPathFromURL();
      if (feedURL) {
        this.getFlatplan( feedURL );
      }
    }
  };

  var Feed = {

    getFeedPathFromURL: function() {
      var query = window.location.search;

      if ( !_.isNull(query) && query !== '' && query !== '?' ) {
        query = query.substring(1); // strip initial ?
        var parts = query.split('&');
        for (var i=0; i<parts.length; i++) {
          var pair = parts[i].split('=');
          if (pair[0] === 'atom') {
            this.atomUrl = decodeURIComponent(pair[1]); /* + '?' + rand*/
          } else if (pair[0] === 'opds') {
            this.opdsUrl = decodeURIComponent(pair[1]); /* + '?' + rand*/
          }
        }
      } else {
        document.querySelector('.form-get-feed').classList.remove('is-hidden');
        document.querySelector('.form-settings').classList.add('is-hidden');
        document.querySelector('.submit-form').classList.add('is-hidden');
        return false;
      }
      return this.atomUrl;
    }

  };


  var Article = Backbone.Model.extend({

    defaults: {
      id: '',
      title: '',
      htmlPath: ''
    }

  });


  var Articles = Backbone.Collection.extend({
    parse: function(response) {
      var parsed = [],
        sections = [];
      $(response).find('entry').each(function(index) {
          var entry = $(this),
            id = entry.find('id').text(),
            title = entry.find('title').text(),
            categories = entry.find('category'),
            links = entry.find('link'),
            snapshotPath = '',
            section = '',
            level = '',
            tag = '',
            color = '',
            editPath = '',
            viewPath = ''

          links.each(function() {
            var link = $(this),
              relAttribute = link.attr('rel'),
              pathArray = articles.url.split( '/' ),
              urlRoot = pathArray[0] + '//' + pathArray[2],
              idArray = id.split('-'),
              idNumber = idArray[1];
            if (relAttribute === 'alternate') {
              viewPath = articles.url + '/../' +link.attr('href');  // TODO remove this hack, get the actual feed root
              editPath = urlRoot + '/wp-admin/post.php?post=' + idNumber + '&action=edit';
            }
            if (relAttribute === 'thumbnail') {
              snapshotPath = articles.url + '/../' +link.attr('href'); // TODO remove this hack, get the actual feed root
            }
          });

          // to improve: look at prettifyCategorySchemes in WR
          categories.each(function() {
            var category = $(this),
              scheme = category.attr('scheme'),
              schemaUrlStart = 'http://schema.pugpig.com/',
              termAttr = category.attr('term');
            switch (scheme) {
            case schemaUrlStart + 'section':
              section = termAttr;
              if(termAttr !== '') {
                sections.push(termAttr);
              }
              break;
            case schemaUrlStart + 'level':
              level = termAttr;
              break;
            case schemaUrlStart + 'tag':
              tag = termAttr;
              break;
            case schemaUrlStart + 'color':
              color = termAttr;
              break;
            }
          });

          parsed.push({
            id: id,
            title: title,
            viewPath: viewPath,
            editPath: editPath,
            snapshotPath: snapshotPath,
            section: section,
            level: level,
            tag: tag,
            color: color,
            index: index
          });
        });

      // get the section from each article, in an array, then make it unique
      var customSections = [];
      $.each(sections, function(i, el){
        if($.inArray(el, customSections) === -1) {
          customSections.push(el);
        }
      });
      customSections.unshift('All');
      this.customSections = customSections;
      //console.log(parsed);
      return parsed;
    },


    getBySection: function( section )  {
      var filtered = this.filter(function(article) {
        return article.get('section') === section;
      });

      // var filtered = this.collection.where({section : section});


      return filtered.map(function(model) {
        return model.toJSON();
      });
    },


    fetch: function(options) {
      options = options || {};
      options.dataType = 'xml';
      return Backbone.Collection.prototype.fetch.call(this, options);
    },

    initialize: function() {
      console.log('coll init');
      this.on('change:index', function() {
        //console.log('index changed');
        this.sort();
      }, this);
    },

    model: Article

  });


  var ArticlesView = Backbone.View.extend({

    el: '<ul class="flatplan is-sortable connected-sortable" data-thumb-size="2">',

    template: _.template($('.article_view').html()),

    filterBySection: function( section ) {
      if (section === 'All'){
        this.$el.html(this.template({
          articles: this.collection.toJSON()
        }));
      } else {
        this.$el.html(this.template({
          articles: this.collection.getBySection( section )
        }));
      }
      return this;

    },

    initialize: function() {


      this.listenTo(this.collection, 'reset', this.render);
      // this.listenTo(this.collection, 'all', function(event){
      //   console.log(event);
      // });

      this.$el.sortable({
        connectWith: '.connected-sortable',
        update: _.bind (function( event, ui) {

          var ids = this.$el.sortable('toArray');
          _.each (ids, function (id, i) {
            // console.log(id);
            var model = this.collection.findWhere ({
              id: id
            });

            // it's a new article
            if (_.isUndefined (model)) {
              // console.log ('new index:', i);
              var advertModel = APP.adverts.findWhere ({
                id: ui.item.data('advert-id')
              });

              this.collection.add ({
                id: ui.item.data('advert-id').toString(),
                index: i,
                title: advertModel.get('ProductDescription'),
                //viewPath: viewPath,
                snapshotPath: advertModel.get('portrait_preview_image'),
                section: 'advert',
                //level: level,
                //tag: tag,
                color: '#5ab3e8'
              }, {
                silent: true
              });


            } else {
              model.set ('index', i);
              this.$el.find ('li#' + id + ' .flatplan-item__index').text ('Page: ' + (parseInt(i, 10) +1));
            }

          }, this);
          this.render();
          //console.log (this.collection.toJSON());
        }, this),
      }).disableSelection();


    },




    render: function() {
      this.collection.each (function (model) {
        // console.log (model.get('id'), model.get('index'));
      })
      // console.table (this.collection.pluck ('index'), this.collection.pluck('index'))
      this.$el.html(this.template({
        articles: this.collection.toJSON()
      }));
      return this;
    }

  });


  // this should be disabled if there is no plan loaded
  var SubmitFormView = Backbone.View.extend({

    events: {
      'submit': 'submit'
    },

    initialize: function() {
      this.input = this.$('.form-update-edition');
    },

    submit: function (e) {
      e.preventDefault();
      var sortedIDs = $( '.is-sortable' ).sortable( 'toArray' ),
        overlay = document.querySelector('.overlay-panel');
      //console.log(sortedIDs);
      $.ajax({
        type: 'POST',
        url: 'http://demo.pugpig.com/tlshub/ping.php',
        data: JSON.stringify(sortedIDs),
        success: function() {
          overlay.classList.toggle('is-hidden');
          setTimeout(function(){
            overlay.classList.add('is-hidden');
          }, 1000);
        }
      });

    }

  });


  var GetFeedFormView = Backbone.View.extend({

    events: {
      'submit': 'submit'
    },

    initialize: function () {
      this.input = this.$('.form-get-feed__input');
    },

    submit: function (e) {
      e.preventDefault();
      document.querySelector('.form-get-feed').classList.add('is-hidden');
      document.querySelector('.form-settings').classList.remove('is-hidden');
      document.querySelector('.submit-form').classList.remove('is-hidden');
      if (this.input.val()) {
        articles.url = this.input.val();
      } else {
        articles.url = 'http://newscientist.wpnow.demo.pugpig.com/editionfeed/17/pugpig_atom_contents.manifest';
      }
      APP.getFlatplan(articles.url);
    }

  });


  var SectionSettingsView = Backbone.View.extend({

    tagName: 'select',
    className: 'form-settings__filter-by-section-select',
    attributes: {
      name: 'select'
    },

    template: _.template($('.section_select_view').html()),

    serializeData: function () {
      return this.options.sections;
    },

    render: function() {
      this.$el.html(this.template({
        sections: this.serializeData()
      }));
      return this;
    }

  });


  var SettingsFormView = Backbone.View.extend({

    el: '.form-settings',

    events: {
      'change .form-settings__show-section-colours': 'toggleSectionColours',
      'change .form-settings__show-linear-view': 'showLinearView',
      'change .form-settings__hide-draft-items': 'hideDraftItems',
      'change .form-settings__filter-by-section select': 'filterArticles',
      'change .form-settings__thumbnail-size input': 'changeThumbSize'
    },

    initialize: function(){
      this.bodyPanel = document.querySelector('.body-panel');
    },
    toggleSectionColours: function() {
      this.bodyPanel.classList.toggle('body-panel--section-colours');
    },
    showLinearView: function() {
      this.bodyPanel.classList.toggle('body-panel--linear');
    },
    hideDraftItems: function() {
      this.bodyPanel.classList.toggle('body-panel--hide-drafts');
    },
    changeThumbSize: function() {
      var size = this.$('.form-settings__thumbnail-size-input').val();
      document.querySelector('.is-sortable').setAttribute('data-thumb-size', size);
    },
    filterArticles: function(e) {
      e.preventDefault();
      var section = this.$('.form-settings__filter-by-section-select').val();
      APP.view.filterBySection( section );
    }
  });


  var Advert = Backbone.Model.extend();


  var Adverts = Backbone.Collection.extend({
    model: Advert,
    url: '/vfp/scripts/newscientist-adverts.json'
    //url: 'http://specle.net/my_incoming_ads.json?state=delivered&publication_format=digital&api_key=f1a58301b55b7ea5b3eb94dca0d37eef91c86874'
  });


  var SidePanelView = Backbone.View.extend({

    el: '.js-side-panel-control',

    events: {
      'click .side-panel__header': 'toggleVisiblity'
    },

    template: _.template($('.advert-template').html()),

    initialize: function(){
      this.el.classList.remove('is-hidden');
      $('.side-panel__items').sortable({
        connectWith: '.connected-sortable'
      }).disableSelection();
    },

    toggleVisiblity: function() {
      this.el.classList.toggle('is-away');
      document.querySelector('.body-panel').classList.toggle('pull-right');
    },

    render: function() {
      //console.log (this.collection.toJSON());
      $('.side-panel__items').html(this.template({
        adverts: this.collection
      }));
      return this;
    }
  });


  var articles = new Articles();


  document.addEventListener('DOMContentLoaded', function() {
    APP.initialize();
  });



})();