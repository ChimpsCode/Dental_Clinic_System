$(document).ready(function() {
    
    function initTeethToggle() {
        $('#adultToggle').on('click', function() {
            showAdultTeeth();
            $(this).addClass('active');
            $('#childToggle').removeClass('active');
        });
        
        $('#childToggle').on('click', function() {
            showChildTeeth();
            $(this).addClass('active');
            $('#adultToggle').removeClass('active');
        });
    }
    
    function showAdultTeeth() {
        const adultTeeth = {
            upper: [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
            lower: [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38]
        };
        
        $('#upperJaw').empty();
        $('#lowerJaw').empty();
        
        adultTeeth.upper.forEach(toothNum => {
            $('#upperJaw').append('<div class="tooth" data-tooth="' + toothNum + '">' + toothNum + '</div>');
        });
        
        adultTeeth.lower.forEach(toothNum => {
            $('#lowerJaw').append('<div class="tooth" data-tooth="' + toothNum + '">' + toothNum + '</div>');
        });
        
        initToothClick();
    }
    
    function showChildTeeth() {
        const childTeeth = {
            upper: [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
            lower: [85, 84, 83, 82, 81, 71, 72, 73, 74, 75]
        };
        
        $('#upperJaw').empty();
        $('#lowerJaw').empty();
        
        childTeeth.upper.forEach(toothNum => {
            $('#upperJaw').append('<div class="tooth" data-tooth="' + toothNum + '">' + toothNum + '</div>');
        });
        
        childTeeth.lower.forEach(toothNum => {
            $('#lowerJaw').append('<div class="tooth" data-tooth="' + toothNum + '">' + toothNum + '</div>');
        });
        
        initToothClick();
    }
    
    function initToothClick() {
        $('.tooth').off('click').on('click', function() {
            const tooth = $(this);
            
            if (tooth.hasClass('selected')) {
                tooth.removeClass('selected');
            } else {
                tooth.addClass('selected');
                
                // Add a pulse effect when clicked
                tooth.css('animation', 'toothPulse 0.3s ease-out');
                setTimeout(function() {
                    tooth.css('animation', '');
                }, 300);
            }
            
            const selectedTeeth = $('.tooth.selected').map(function() {
                return $(this).data('tooth');
            }).get();
            
            console.log('Selected teeth:', selectedTeeth);
        });
    }
    
    function initTabs() {
        $('.tab-btn').on('click', function() {
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            
            const tabText = $(this).text().trim();
            console.log('Tab selected:', tabText);
        });
    }
    
    function initTimelineHover() {
        $('.timeline-item').hover(
            function() {
                $(this).find('.timeline-content').addClass('highlight');
            },
            function() {
                $(this).find('.timeline-content').removeClass('highlight');
            }
        );
    }
    
    function initServiceHover() {
        $('.service-item').hover(
            function() {
                $(this).find('.service-name').css('color', '#2563eb');
            },
            function() {
                $(this).find('.service-name').css('color', '#1f2937');
            }
        );
    }
    
    initTeethToggle();
    initTabs();
    initTimelineHover();
    initServiceHover();
    
    console.log('Patient Details page initialized');
    console.log('Adult teeth loaded by default');
});
