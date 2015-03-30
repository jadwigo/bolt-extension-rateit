module.exports = function(grunt) {

	grunt.initConfig({
		uglify : {
			my_target : {
				options : {
					sourceMap : true,
					sourceMapName : 'js/bolt.rateit.min.map',
					preserveComments : 'some'
				},
				files : {
					'js/bolt.rateit.min.js' : [
						'js/jquery.rateit.js',
						'js/cookies.js',
						'js/init.js' 
					]
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-uglify');

	grunt.registerTask('default', [ 'uglify' ]);
};