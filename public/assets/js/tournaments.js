/**
 * Tournament Discovery Interface
 * Handles filtering, modals, pagination, and progressive enhancement
 */

(function($) {
    'use strict';
    
    // Tournament management object
    window.TournamentManager = {
        currentFilters: {
            mode: '',
            rank_range: 'All',
            status: 'All',
            search: ''
        },
        currentLimit: 10,
        currentOffset: 0,
        isLoading: false,
        
        // Initialize the tournament interface
        init: function() {
            this.bindFilterEvents();
            this.bindModalEvents();
            this.bindPaginationEvents();
            this.setupProgressiveEnhancement();
            console.log('Tournament Manager initialized');
        },
        
        // Bind filter interaction events
        bindFilterEvents: function() {
            const self = this;
            
            // Search input with debouncing
            let searchTimeout;
            $('#tournament-search').on('input', function() {
                clearTimeout(searchTimeout);
                const searchValue = $(this).val().trim();
                searchTimeout = setTimeout(function() {
                    self.updateFilter('search', searchValue);
                }, 300);
            });
            
            // Rank range dropdown
            $('#rank-range-filter').on('change', function() {
                self.updateFilter('rank_range', $(this).val());
            });
            
            // Registration status radio buttons
            $('input[name="registration-status"]').on('change', function() {
                if ($(this).is(':checked')) {
                    self.updateFilter('status', $(this).val());
                }
            });
            
            // Game mode checkboxes (single selection for now)
            $('input[name="game-mode"]').on('change', function() {
                if ($(this).is(':checked')) {
                    // Uncheck others for single selection
                    $('input[name="game-mode"]').not(this).prop('checked', false);
                    self.updateFilter('mode', $(this).val());
                } else {
                    self.updateFilter('mode', '');
                }
            });
            
            // Clear all filters
            $('#clear-filters').on('click', function(e) {
                e.preventDefault();
                self.clearAllFilters();
            });
            
            // Mobile filter toggle
            $('#mobile-filter-toggle').on('click', function() {
                $('.filter-panel').toggleClass('filter-panel-open');
            });
        },
        
        // Bind modal interaction events
        bindModalEvents: function() {
            const self = this;
            
            // Open modal when clicking tournament cards (not titles)
            $(document).on('click', '.tournament-card', function(e) {
                // Don't open modal if clicking on the title link
                if ($(e.target).closest('.tournament-title').length) {
                    return true; // Allow link to open
                }
                
                e.preventDefault();
                const tournamentId = $(this).data('tournament-id');
                if (tournamentId) {
                    self.openTournamentModal(tournamentId);
                }
            });
            
            // Close modal events
            $('.modal-close, .modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // ESC key to close modal
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('.tournament-modal').hasClass('modal-open')) {
                    self.closeModal();
                }
            });
        },
        
        // Bind pagination events
        bindPaginationEvents: function() {
            const self = this;
            
            // Show more buttons
            $('.show-more-btn').on('click', function() {
                const newLimit = parseInt($(this).data('limit'));
                self.showMore(newLimit);
            });
        },
        
        // Setup progressive enhancement features
        setupProgressiveEnhancement: function() {
            // Lazy loading for images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.classList.remove('lazy');
                                imageObserver.unobserve(img);
                            }
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            // Add loading states
            this.setupLoadingStates();
        },
        
        // Update a specific filter and refresh results
        updateFilter: function(filterKey, value) {
            this.currentFilters[filterKey] = value;
            this.currentOffset = 0; // Reset pagination
            this.loadTournaments();
            this.updateFilterDisplay();
        },
        
        // Clear all filters
        clearAllFilters: function() {
            this.currentFilters = {
                mode: '',
                rank_range: 'All',
                status: 'All',
                search: ''
            };
            this.currentOffset = 0;
            
            // Reset form elements
            $('#tournament-search').val('');
            $('#rank-range-filter').val('All');
            $('input[name="registration-status"]').prop('checked', false);
            $('input[name="registration-status"][value="All"]').prop('checked', true);
            $('input[name="game-mode"]').prop('checked', false);
            
            this.loadTournaments();
            this.updateFilterDisplay();
        },
        
        // Load tournaments via AJAX
        loadTournaments: function(append = false) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoadingState();
            
            const params = new URLSearchParams({
                limit: this.currentLimit,
                offset: this.currentOffset
            });
            
            // Add active filters
            Object.keys(this.currentFilters).forEach(key => {
                if (this.currentFilters[key] && this.currentFilters[key] !== 'All') {
                    params.append(key, this.currentFilters[key]);
                }
            });
            
            $.get('/api/tournaments.php?' + params.toString())
                .done((response) => {
                    if (response.success) {
                        if (append) {
                            this.appendTournaments(response.tournaments);
                        } else {
                            this.replaceTournaments(response.tournaments);
                        }
                        this.updatePaginationButtons(response.pagination);
                    } else {
                        this.showError(response.error || '토너먼트를 불러오는 중 오류가 발생했습니다.');
                    }
                })
                .fail(() => {
                    this.showError('서버 연결 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
                })
                .always(() => {
                    this.isLoading = false;
                    this.hideLoadingState();
                });
        },
        
        // Replace tournament list with new data
        replaceTournaments: function(tournaments) {
            const container = $('.tournament-grid');
            container.empty();
            
            if (tournaments.length === 0) {
                container.html(this.getEmptyStateHtml());
                return;
            }
            
            tournaments.forEach(tournament => {
                container.append(this.createTournamentCard(tournament));
            });
            
            // Re-initialize lazy loading for new images
            this.setupProgressiveEnhancement();
        },
        
        // Append tournaments to existing list
        appendTournaments: function(tournaments) {
            const container = $('.tournament-grid');
            
            tournaments.forEach(tournament => {
                container.append(this.createTournamentCard(tournament));
            });
            
            // Re-initialize lazy loading for new images
            this.setupProgressiveEnhancement();
        },
        
        // Create tournament card HTML
        createTournamentCard: function(tournament) {
            const bannerHtml = tournament.banner_url 
                ? `<img data-src="${tournament.banner_url}" alt="${tournament.title} 배너" class="tournament-banner lazy" loading="lazy">`
                : '<div class="tournament-banner-placeholder">🏆</div>';
                
            return `
                <div class="tournament-card" data-tournament-id="${tournament.id}">
                    ${bannerHtml}
                    <div class="tournament-content">
                        <h3 class="tournament-title">
                            <a href="${tournament.forum_url}" target="_blank" rel="noopener">
                                ${tournament.title}
                            </a>
                        </h3>
                        <div class="tournament-meta">
                            <span class="tournament-host">주최: ${tournament.host}</span>
                            <span class="tournament-mode">${tournament.mode}</span>
                        </div>
                        <div class="tournament-details">
                            <span class="rank-range">랭크: ${tournament.rank_range}</span>
                            <span class="team-info">${tournament.team_info}</span>
                        </div>
                        <div class="tournament-status">
                            <span class="status-badge status-${tournament.status_class}">
                                ${tournament.registration_status}
                            </span>
                        </div>
                    </div>
                </div>
            `;
        },
        
        // Show more tournaments (pagination)
        showMore: function(newLimit) {
            this.currentLimit = newLimit;
            this.currentOffset = 0;
            this.loadTournaments();
        },
        
        // Open tournament detail modal
        openTournamentModal: function(tournamentId) {
            // Find tournament data from current page
            const tournamentData = this.findTournamentById(tournamentId);
            if (!tournamentData) {
                console.error('Tournament not found:', tournamentId);
                return;
            }
            
            this.populateModal(tournamentData);
            
            // Preserve scroll position
            this.scrollPosition = window.pageYOffset;
            
            // Open modal
            $('.tournament-modal').addClass('modal-open');
            $('body').addClass('modal-open');
        },
        
        // Close tournament modal
        closeModal: function() {
            $('.tournament-modal').removeClass('modal-open');
            $('body').removeClass('modal-open');
            
            // Restore scroll position
            if (this.scrollPosition !== undefined) {
                window.scrollTo(0, this.scrollPosition);
            }
        },
        
        // Populate modal with tournament data
        populateModal: function(tournament) {
            $('.modal-title').text(tournament.title);
            $('.modal-host').text('주최자: ' + tournament.host);
            $('.modal-mode').text(tournament.mode);
            $('.modal-rank-range').text(tournament.rank_range);
            $('.modal-status').text(tournament.registration_status)
                .removeClass().addClass('status-badge status-' + tournament.status_class);
            
            // Banner
            const bannerContainer = $('.modal-banner');
            if (tournament.banner_url) {
                bannerContainer.html(`<img src="${tournament.banner_url}" alt="${tournament.title} 배너" class="modal-banner-img">`);
            } else {
                bannerContainer.html('<div class="modal-banner-placeholder">🏆</div>');
            }
            
            // Links
            const linksContainer = $('.modal-links');
            linksContainer.empty();
            
            linksContainer.append(`<a href="${tournament.forum_url}" target="_blank" rel="noopener" class="modal-link">포럼 게시글 보기</a>`);
            
            if (tournament.google_form_id) {
                linksContainer.append(`<a href="https://docs.google.com/forms/d/${tournament.google_form_id}" target="_blank" rel="noopener" class="modal-link">참가 신청</a>`);
            }
            
            if (tournament.discord_link) {
                linksContainer.append(`<a href="${tournament.discord_link}" target="_blank" rel="noopener" class="modal-link">디스코드 참여</a>`);
            }
        },
        
        // Find tournament by ID in current data
        findTournamentById: function(id) {
            // This would need to store tournament data or make API call
            // For now, return placeholder
            return {
                id: id,
                title: 'Tournament Title',
                host: 'Host Name',
                mode: 'osu! Standard',
                rank_range: '#1,000 - #5,000',
                registration_status: '참가 모집 중',
                status_class: 'open',
                banner_url: null,
                forum_url: 'https://osu.ppy.sh/community/forums/topics/' + id,
                google_form_id: null,
                discord_link: null
            };
        },
        
        // Update filter display
        updateFilterDisplay: function() {
            const activeCount = Object.values(this.currentFilters)
                .filter(val => val && val !== 'All').length;
            
            $('.active-filter-count').text(activeCount > 0 ? `(${activeCount})` : '');
        },
        
        // Update pagination buttons
        updatePaginationButtons: function(pagination) {
            const container = $('.pagination-container');
            container.empty();
            
            if (pagination.has_more) {
                if (this.currentLimit === 10) {
                    container.append('<button class="show-more-btn" data-limit="25">25개 더 보기</button>');
                }
                if (this.currentLimit === 25) {
                    container.append('<button class="show-more-btn" data-limit="50">50개 더 보기</button>');
                }
            }
            
            // Re-bind events for new buttons
            this.bindPaginationEvents();
        },
        
        // Loading state management
        showLoadingState: function() {
            $('.tournament-grid').addClass('loading');
        },
        
        hideLoadingState: function() {
            $('.tournament-grid').removeClass('loading');
        },
        
        setupLoadingStates: function() {
            // Add loading spinners where needed
        },
        
        // Error handling
        showError: function(message) {
            const errorHtml = `
                <div class="error-state">
                    <h3>오류 발생</h3>
                    <p>${message}</p>
                    <button onclick="location.reload()" class="retry-btn">다시 시도</button>
                </div>
            `;
            $('.tournament-grid').html(errorHtml);
        },
        
        // Empty state
        getEmptyStateHtml: function() {
            return `
                <div class="empty-state">
                    <h3>토너먼트를 찾을 수 없습니다</h3>
                    <p>필터 조건을 변경하거나 검색어를 확인해보세요.</p>
                    <button onclick="TournamentManager.clearAllFilters()" class="clear-filters-btn">
                        모든 필터 초기화
                    </button>
                </div>
            `;
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        TournamentManager.init();
    });
    
})(jQuery);