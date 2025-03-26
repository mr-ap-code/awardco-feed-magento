let currentIndex = 0;

function showRecognition(index) {
    const recognitions = document.querySelectorAll('.recognition');
    recognitions.forEach((rec, i) => {
        rec.classList.toggle('active', i === index);
    });
}

function nextRecognition() {
    const recognitions = document.querySelectorAll('.recognition');
    currentIndex = (currentIndex + 1) % recognitions.length;
    showRecognition(currentIndex);
}

function prevRecognition() {
    const recognitions = document.querySelectorAll('.recognition');
    currentIndex = (currentIndex - 1 + recognitions.length) % recognitions.length;
    showRecognition(currentIndex);
}

require(['jquery', 'domReady!'], function($) {
    showRecognition(currentIndex);
    setInterval(nextRecognition, 10000); // Move to next recognition every 10 seconds
    
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
            document.body.classList.add('fullscreen');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                document.body.classList.remove('fullscreen');
            }
        }
    }
    
    $(document).on('mousemove', function(event) {
        const fullscreenIcon = $('.fullscreen-icon');
        if (event.clientY < 50 && event.clientX > window.innerWidth - 50) {
            fullscreenIcon.show();
        } else {
            fullscreenIcon.hide();
        }
    });
    
    $('.fullscreen-icon').on('click', toggleFullscreen);
});
