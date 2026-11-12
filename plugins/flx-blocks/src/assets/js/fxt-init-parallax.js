/**
 * Parallax Background.
 */
const kbjarforEach = function (array, callback, scope) {
	for (let i = 0; i < array.length; i++) {
		callback.call(scope, i, array[i]); // passes back stuff we need.
	}
};

// Function to initialize jarallax on a single element
const initializeParallax = (element) => {
	jarallax(element, {
		speed: flxbk_parallax.speed,
		elementInViewport: element,
	});
};

// Initial parallax setup for elements that are already in the DOM
const fxbNodeList = document.querySelectorAll('.fxt-jarallax');
kbjarforEach(fxbNodeList, function (index, value) {
	initializeParallax(value);
});

// Set up a timer for resize optimization
let kbjartimeout;
window.addEventListener(
	'resize',
	function () {
		if (!kbjartimeout) {
			kbjartimeout = setTimeout(function () {
				kbjartimeout = null;
				document.body.style.setProperty(
					'--fxb-screen-height-fix',
					document.documentElement.clientHeight + 200 + 'px'
				);
			}, 66);
		}
	},
	false
);

// Set initial screen height CSS var
document.body.style.setProperty('--fxb-screen-height-fix', document.documentElement.clientHeight + 200 + 'px');

// Listen for lazy-loaded elements and initialize parallax if needed
window.addEventListener('flxbk-lazy-loaded', ({ detail }) => {
	const el = detail.element;
	if (el.classList.contains('fxt-jarallax')) {
		initializeParallax(el);
	}
});
