$(function() {

    alertify.set('notifier', 'position', 'bottom-center');

    var $languages = $('#languages');
    if ($languages.length > 0) {
        $languages.select2();
    }


    var $thumbnail = $('#thumbnail');
    if ($thumbnail.length > 0) {
        $thumbnail.change(function() {
            var uploadFile = $(this);
            var files = !!this.files ? this.files : [];
            if (!files.length || !window.FileReader) return; // no file selected, or no FileReader support

            if (/^image/.test(files[0].type)) { // only image file
                var reader = new FileReader(); // instance of the FileReader
                reader.readAsDataURL(files[0]); // read the local file

                reader.onloadend = function() { // set image data as background of div
                    //alert(uploadFile.closest(".upimage").find('.imagePreview').length);
                    uploadFile.closest(".imgUp").find('.imagePreview').css("background-image", "url(" + this.result + ")");
                }
            }
        });
    }


    var video_drop_block = $(".upload-video");
    if (video_drop_block.length > 0) {

        if (typeof(window.FileReader)) {
            video_drop_block[0].ondragover = function() {
                video_drop_block.addClass('hover');
                return false;
            };

            video_drop_block[0].ondragleave = function() {
                video_drop_block.removeClass('hover');
                return false;
            };

            video_drop_block[0].ondrop = function(event) {

                event.preventDefault();
                video_drop_block.removeClass('hover');
                var file = event.dataTransfer.files;
                $('#upload_video').find('input').prop('files', file);
            };
        }

        $('.upload-video').click(function() {
            $('.upload-video-file').trigger('click');
        });

        $('#upload_video').find('input').change(function() {
            var file = $('.upload-video-file').prop('files')[0];
            if (file !== undefined) {
                var size;
                if (file.size >= 1073741824) {
                    size = (file.size / 1073741824).toFixed(2) + ' GB';
                } else if (file.size >= 1048576) {
                    size = (file.size / 1048576).toFixed(2) + ' MB';
                } else if (file.size >= 1024) {
                    size = (file.size / 1024).toFixed(2) + ' KB';
                }

                $('.selected-file').text(file.name);
                $('.size').text(size);
                $('.osahan-title').text(file.name);
                $('#upload_btn').removeClass('d-none');
            } else {
                $('.selected-file').text('');
                $('.size').text('');
                $('.osahan-title').text('');
                $('#upload_btn').addClass('d-none');
            }
        });

        $('#upload_btn').click(function(e) {
            e.preventDefault();
            e.stopPropagation();

            var file = $(".upload-video-file").prop('files')[0];
            if (file !== undefined) {
                var file_size = file.size;
                if (file_size > 200 * 1024 * 1024) {
                    swal({
                        title: 'Error',
                        text: "File size too big.",
                        type: 'error',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'OK',
                        buttonsStyling: true,
                        confirmButtonClass: 'btn btn-success',
                    }).then(function() {
                            swal.close();
                            $('.upload-video-file').val('');
                        },
                        function() {
                            swal.close();
                            $('.upload-video-file').val('');
                        });
                    return false;
                } else {
                    var filename = $('.upload').val().split('\\').pop();
                    $('.upload-details').removeClass('d-none');
                    $('.upload').addClass('d-none');
                    $('#upload_video').submit();
                    window.scrollTo(0, 0);
                }
            } else {
                swal({
                    title: 'Error',
                    text: "Please select a file first.",
                    type: 'error',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'OK',
                    buttonsStyling: true,
                    confirmButtonClass: 'btn btn-success',
                })
            }
        });

        var bar = $('.progress-bar');
        var percent = $('.percent');

        $('#upload_video').ajaxForm({
            url: APP_URL + '/podcast/upload',
            dataType: 'json',
            method: 'post',
            beforeSend: function() {
                var percentVal = '0%';
                bar.width(percentVal);
                percent.html(percentVal);
            },
            uploadProgress: function(event, position, total, percentComplete) {
                if (percentComplete > 50) {
                    percent.addClass('white');
                }
                var percentVal = percentComplete + '%';
                bar.width(percentVal);
                percent.html(percentVal);

                if (percentComplete == 100) {
                    $('.progress').find('.bar').removeClass('upload-progress-bar');
                    $('.osahan-desc').text('');
                }
            },
            success: function(data, status, xhr) {
                $('.progress').addClass('hidden');

                if (xhr.status == 201) {
                    $('#submit_btn').prop('disabled', false);
                    $('#podcast_details').attr('action', data.action);
                    $('.upload-video-file').val('');
                    $('#title').val(data.title);
                    if (data.thumbnail.length > 0) {
                        $('.imgplace').css('background-image', 'url(' + data.thumbnail + ')');
                        $('.imagePreview').css('background-image', 'url(' + data.thumbnail + ')');
                    } else {
                        $('#thumbnail').prop('required', true);
                    }

                    // var i       = 0;
                    // var url     = '{img}';
                    // video_thumb = data.images;
                    //
                    // $('.carousel-inner').html('');
                    // $.each(video_thumb, function( index, value ) {
                    //     $('.carousel-inner').append('<div class="item"><img src="' + value + '"></div>');
                    //     i++;
                    // });
                    // $(".carousel-inner").find('.item:first-child').addClass('active');
                    // $("#video-thumnail").val(video_thumb[0]);
                    // $('.carousel').carousel({
                    //     interval: false
                    // });

                    // $("#video-thumnails").find('.item').each(function(index, el) {
                    //    if (i == 0) {
                    //       $("#video-thumnail").val(data.images[i]);
                    //    }

                    //    $(el).html($("<img>",{
                    //       src:url.replace('{img}',data.images[i])
                    //    }));

                    // });
                } else {
                    swal({
                        title: 'Error',
                        text: data.message,
                        type: 'error',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'OK',
                        buttonsStyling: true,
                        confirmButtonClass: 'btn btn-success',
                    }).then(function() {
                            swal.close();
                            $('.upload-video-file').val('');
                        },
                        function() {
                            swal.close();
                            $('.upload-video-file').val('');
                        });
                }
            }
        });


    }

    var categories = $('input[name="categories[]"]');
    if (categories.length > 0) {
        categories.click(function() {
            var checked = categories.filter(':checked');
            if (checked.length >= 6) {
                categories.each(function() {
                    if (!$(this).is(':checked')) {
                        $(this).prop('disabled', true);
                    }
                });
            } else {
                categories.each(function() {
                    if (!$(this).is(':checked')) {
                        $(this).prop('disabled', false);
                    }
                });
            }
        });
    }

    var podcastDetails = $('#podcast_details');
    if (podcastDetails.length > 0) {
        var $submitBtn = $('#submit_btn');
        podcastDetails.ajaxForm({
            url: $(this).attr('action'),
            method: 'post',
            beforeSend: function() {
                $submitBtn.attr('disabled', true);
                $submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Please wait');
            },
            success: function(data) {
                if (data.status == 1) {
                    window.location.href = data.redirect;
                } else {
                    swal({
                        title: 'Error',
                        text: data.message,
                        type: 'error',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'OK',
                        buttonsStyling: true,
                        confirmButtonClass: 'btn btn-success',
                    });
                }
            },
            complete: function() {
                $submitBtn.attr('disabled', false);
                $submitBtn.html('Save Changes');
            }
        });
    }


    /*$('#podcast_details').validate({
        rules: {
            channel: 'required',
            title: 'required',
            description: 'required',
            privacy: 'required',
            license: 'required',
            languages: 'required',
            comments_allowed: 'required',
            tags: 'required',
            categories: 'required',
        },
        submitHandler: function (form) {
            console.log($(form), typeof $(form));
            console.log($(form).attr('action'));
            $(form).ajaxForm({
                url: $(form).attr('action'),
                method: 'post',
                beforeSend: function() {
                    $('#submit_btn').attr('disabled', true);
                    $('#submit_btn').html('<i class="fa fa-spinner fa-spin"></i> Please wait');
                },
                success: function(data) {
                    if (data.status == 200) {
                        window.location.href = data.link;
                    }
                    else if(data.status == 402){
                        swal({
                            title: 'Error',
                            text: data.message,
                            type: 'error',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'OK',
                            buttonsStyling: true,
                            confirmButtonClass: 'btn btn-success',
                        });
                    }
                    else {
                        $('#submit_btn').attr('disabled', false);
                        $('#submit_btn').html('Save Changes');
                    }
                }
            });
        }
    });*/


    // fluidPlayer method is global when CDN distribution is used.
    var player = fluidPlayer('full-screen-video');


    if ($('#single-video').length > 0) {
        fluidPlayer('single-video');
        // fluidPlayer method is global when CDN distribution is used.
        var player = fluidPlayer('example-player');
    }

    var $singleAudio = $('#single-audio');
    if ($singleAudio.length > 0) {
        var wavesurfer = WaveSurfer.create({
            container: '#single-audio'
        });
        wavesurfer.load($singleAudio.data('source'));

        $('#backward').click(function() {
            wavesurfer.skipBackward();
        });

        $('#playpause').click(function() {
            wavesurfer.playPause();
        });

        $('#forward').click(function() {
            wavesurfer.skipForward();
        });

        $('#mute').click(function() {
            wavesurfer.toggleMute();
        });


    }

    $('#subscribe_btn').click(function() {
        $.ajax({
            url: APP_URL + '/channel/' + $(this).data('channel') + '/subscribe',
            method: 'post',
            data: { _token: $('meta[name=csrf-token]').attr('content'), _method: 'put' },
            dataType: 'json',
            beforeSend: function() {

            },
            success: function(data) {
                if (data.status == 1) {
                    if (data.action == 'subscribed') {
                        $('.sub-status').text('Unsubscribe');
                    } else {
                        $('.sub-status').text('Subscribe');
                    }
                    $('.sub-count').text(data.subsCount);
                }
            },
            error: function() {

            },
            complete: function() {

            }
        });
    });

    $('#like_btn').click(function() {
        var $this = $(this);
        $.ajax({
            url: APP_URL + '/podcast/' + $this.data('podcast') + './like',
            method: 'post',
            data: { _token: $('meta[name=csrf-token]').attr('content'), _method: 'put' },
            dataType: 'json',
            beforeSend: function() {

            },
            success: function(data) {
                if (data.status == 1) {
                    if (data.action == 'liked') {
                        $this.removeClass('btn-outline-danger').addClass('btn-danger');
                    } else {
                        $this.removeClass('btn-danger').addClass('btn-outline-danger');
                    }
                    $('#likes_count').text(data.likesCount);
                }
            },
            error: function() {

            },
            complete: function() {

            }
        });
    });

    $('#comment_form').submit(function(e) {
        e.preventDefault();
        e.stopPropagation();

        $.ajax({
            url: $(this).attr('action'),
            method: 'post',
            data: $(this).serialize(),
            dataType: 'json',
            beforeSend: function() {

            },
            success: function(data) {
                if (data.status == 1) {
                    var comment = '<div class="card gedf-card mt-4">\n' +
                        '                        <div class="card-header">\n' +
                        '                            <div class="d-flex justify-content-between align-items-center">\n' +
                        '                                <div class="d-flex justify-content-between align-items-center">\n' +
                        '                                    <div class="mr-2">\n' +
                        '                                        <img class="rounded-circle" width="45" src="' + $('.nav-avatar').attr('src') + '" alt="">\n' +
                        '                                    </div>\n' +
                        '                                    <div class="ml-2">\n' +
                        '                                        <div class="h5 m-0">@LeeCross</div>\n' +
                        '                                        <div class="h7 text-muted">' + $('.nav-user').text() + '</div>\n' +
                        '                                    </div>\n' +
                        '                                </div>\n' +
                        '\n' +
                        '                            </div>\n' +
                        '\n' +
                        '                        </div>\n' +
                        '                        <div class="card-body">\n' +
                        '                            <div class="text-muted h7 mb-2"> <i class="fa fa-clock-o"></i> 1 second ago</div>\n' +
                        '\n' +
                        '\n' +
                        '                            <p class="card-text">' + $('#message').val() + '</p>' +
                        '                        </div>\n' +
                        '                        <div class="card-footer">\n' +
                        '                            <a href="#" class="card-link"><i class="fa fa-gittip"></i> 0 Like</a>\n' +
                        '\n' +
                        (data.own == 1 ? '<a href="#" class="card-link delete-comment float-right">Delete</a>' : '') +
                        '                        </div>\n' +
                        '                    </div>';
                    $('#comments').prepend($(comment));
                    $('#message').val('');
                } else {

                }
            },
            error: function() {

            },
            complete: function() {

            }
        });

    });


    $('#comments').on('click', '.like-comment', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var comment = $(e.target).closest('.comment-single');
        $.ajax({
            url: APP_URL + '/podcast/comment/' + comment.data('comment') + '/like',
            method: 'post',
            data: { _token: $('meta[name=csrf-token]').attr('content') },
            dataType: 'json',
            success: function(data) {
                if (data.status == 1) {
                    comment.find('.like-txt').text('Liked');
                    comment.find('.like-count-txt').text(data.likes);
                    alertify.success(data.message);
                } else {
                    alertify.error(data.message);
                }
            }
        });
    });


    $('#comments').on('click', '.delete-comment', function(e) {
        e.preventDefault();
        e.stopPropagation();

        alertify.confirm('Are you sure?', 'You are about to delete this comment.', function() {
            var comment = $(e.target).closest('.comment-single');
            $.ajax({
                url: APP_URL + '/podcast/comment/' + comment.data('comment'),
                method: 'post',
                data: { _method: 'delete', _token: $('meta[name=csrf-token]').attr('content') },
                dataType: 'json',
                success: function(data) {
                    if (data.status == 1) {
                        comment.fadeOut(500, function() {
                            $(this).remove();
                        });
                        alertify.success(data.message);
                    } else {
                        alertify.error(data.message);
                    }
                }
            });
        }, function() {
            //
        }).set({ labels: { ok: 'Delete', cancel: 'Cancel' } });

    });

    $('#more_comments').click(function(e) {
        e.preventDefault();
        e.stopPropagation();

        var $this = $(this);
        $.ajax({
            url: $this.attr('href'),
            method: 'get',
            dataType: 'json',
            beforeSend: function() {
                $this.html('<i class="fa fa-spinner fa-spin"></i> Loading');
            },
            success: function(data) {
                $('#comments').append(data.comments);
                if (data.count > 0) {
                    $this.attr('href', data.url);
                    $this.html('Load More');
                } else {
                    $this.remove();
                }
            },
            error: function() {
                $this.html('Load More');
                alertify.error('An error occurred. Please try again.');
            }
        });
    });

});