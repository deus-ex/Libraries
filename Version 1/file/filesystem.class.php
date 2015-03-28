<?php

/**
*
* This class handles file system from image/file upload, directory listing, 
* directory/file manipulation (delete, create, rename etc)
*
* @since       Version .1.0
* @author      Jencube Dev. Team
* @license     http://opensource.org/licenses/gpl-license.php 
*              GNU General Public License (GPL)
* @copyright   Copyright (c) 2013 - 2014, Jencube
* @link
*
*/

  class Filesystem {

    private $file;
    private $fileName;
    private $fileSize;
    private $fileMIME;
    private $fileExtension;
    private $uploadedFile;

    public $maxFileSize = 1048576;    // Max file size is 1MB ( 1MB = 1024KB, 1KB = 1024Byte )
    public $rename = TRUE;
    public $imageType = 'jpg';
    public $resizeWidth = 170;
    public $resizeHeight = 170;
    public $uploadDirectory = '/';
    public $validFiles = array(
                                'jpeg',
                                'jpg',
                                'png',
                                'gif',
                                'bmp',
                                'tiff'
                               );

    private $originalImage;
    private $resizedImage;

    private $errors = array();
    private $confirmation = NULL;

    /**
    *
    * Construct
    *
    * @access   public
    * @param    array  $options - to set the file options
    *                             array (
    *                                     maxsize => int
    *                                     rename => bool
    *                                     resizeheight => int
    *                                     resizewidth => int
    *                                     directory => string
    *                                     directory => string
    *                                     imagetype => string
    *                                     validfiles => array
    *                             )
    *
    */

    public function __construct( $options = array() ) {
      if ( isset( $options['maxsize'] ) )
        $this->maxFileSize = $options['maxsize'];

      if ( isset( $options['rename'] ) )
        $this->rename = $options['rename'];

      if ( isset( $options['resizeheight'] ) )
        $this->resizeHeight = $options['resizeheight'];

      if ( isset(  $options['resizewidth'] ) )
        $this->resizeWidth = $options['resizewidth'];

      if ( isset( $options['directory'] ) )
        $this->uploadDirectory = $options['directory'];

      if ( isset( $options['imagetype'] ) )
        $this->imageType = $options['imagetype'];

      if ( isset( $options['validfiles'] ) )
        $this->validFiles = $options['validfiles'];
    }



    private function get_file_extension( $fileName = NULL ) {
      if ( !empty( $fileName ) )
        $this->fileName = $fileName;

      if ( !$this->is_file_link( $this->fileName  ) ) {
        $getExt = strrchr( stripslashes( $this->fileName ), '.' );
        $extension = str_replace( '.', '', $getExt );
      } else {
        $extension = @pathinfo( $this->fileName, PATHINFO_EXTENSION );
      }

      return strtolower( $extension );
    }

    private function is_file_link( $file ) {
      $parts = explode( '/', $file );
      if ( !is_array( $parts ) ) {
        return FALSE;
      }
      return TRUE;
    }

    private function validate() {

      if ( !$this->is_extension_valid() ) {
        $this->errors[] .= 'invalid_file';
        return FALSE;
      }

      if ( $this->fileSize > $this->maxFileSize ) {
        $this->errors[] .= 'invalid_size';
        return FALSE;
      }

      return TRUE;
    }


    public function is_extension_valid( $file = NULL ) {
      if ( !empty ( $file ) )
        $this->fileExtension = $this->get_file_extension( $file );

      if ( in_array( strtolower( $this->fileExtension ), $this->validFiles ) ) {
        return TRUE;
      }
      return FALSE;
    }

    public function change_file_name( $oldName, $newName ) {
      if ( empty( $oldName ) || empty( $newName ) )
        return FALSE;

      if ( !file_exists( $oldName ) ) {
        return FALSE;
      } else {
        return ( rename( $oldName, $newName ) ) ? TRUE : FALSE;
      }
      @clearstatcache();
    }

    private function generate_filename( $file, $length = 10 ) {
      if ( $this->rename == TRUE ) {
        $fileName = '';
        $keys = array_merge( range( 0, 9 ), range( 'a', 'z' ) );

        for ( $i = 0; $i < $length; $i++ ) {
            $fileName .= $keys[ array_rand( $keys ) ];
        }

        if ( file_exists( $this->uploadDirectory . $fileName . '.' . $this->fileExtension ) ) {
          $this->generate_filename( $file );
        } else {
          return $fileName;
        }
      }
      return $this->get_file_name( $file );
    }

    public function upload( $file, $type = NULL ) {
      $uploaded = NULL;

      if ( !is_array( $file ) ) {

        $fileContent = @file_get_contents( $file, true );

        if ( $fileContent === FALSE ) {
          $this->errors[] .= 'file_not_found';
          return FALSE;
        }

        $this->fileExtension = $this->get_file_extension( $file );
        $this->fileSize = $this->file_size( $file );
        $this->fileName = $this->generate_filename( $file );
        $this->file = $this->fileName . '.' . $this->fileExtension;

        if ( $this->validate() ) {
          $uploaded = @file_put_contents( $this->uploadDirectory . $this->file, $fileContent );
        }

      } else {

        $this->uploadedFile = $file['tmp_name'];
        $this->fileMIME = $file['type'];
        $this->fileSize = $this->file_size( $this->uploadedFile );
        $this->fileExtension = $this->get_file_extension( $file['name'] );
        $this->fileName = $this->generate_filename( $file['name'] );
        $this->file = $this->fileName . '.' . $this->fileExtension;

        if ( $this->validate() ) {
          $uploaded = move_uploaded_file( $this->uploadedFile, $this->uploadDirectory . $this->file );
        }

      }
      $this->confirmation = 'uploaded';
      return ( isset( $uploaded ) || $uploaded == TRUE ) ? $this->file : FALSE;

    }

    public function file_size( $file ) {
      return @filesize( $file );
    }

    public function get_file_name( $path, $suffix = NULL ) {
      if ( is_file( $path ) ) {

        if ( empty( $suffix ) ) {

          $parts = explode( '/', $path );
          $filename = $parts[ count( $parts ) - 1 ];
          $extension = $this->get_file_extension( $filename );
          return basename( $path, $extension );

        } else {

          return basename( $path, $suffix );

        }

      } else {

        return basename( $path );

      }
    }

    public function create_directory( $directoryName, $permission = 0777 ) {
      if ( !is_dir( $directoryName ) ) {
        @mkdir( $directoryName, $permission );
        @chmod( $directoryName, $permission );
      }
    }

    public function rename_directory( $oldName, $newName, $create = FALSE ) {
      if ( empty( $oldName ) || empty( $newName ) )
        return FALSE;

      if ( !is_dir( $oldName ) ) {
        if ( $create ) {
          $this->create_directory( $newName );
        } else {
          return FALSE;
        }
      } else {
        return ( rename( $oldName, $newName ) ) ? TRUE : FALSE;
      }
      @clearstatcache();
    }

    public function create_image( $image ) {
      switch ( strtolower( $this->get_file_extension( $image ) ) ) {
        case 'jpg':
        case 'jpeg':
          $sourceImage = @imagecreatefromjpeg( $image );
          break;
        case 'png':
          $sourceImage = @imagecreatefrompng( $image );
          break;
        case 'gif':
          $sourceImage = @imagecreatefromgif( $image );
          break;
        case 'bmp':
          $sourceImage = @imagecreatefromwbmp( $image );
          break;
        default:
          $sourceImage = FALSE;
          break;
      }
      @imagedestroy( $image );
      return $sourceImage;
    }

    public function resize_image( $image, $resizeWidth = NULL, $resizeHeight = NULL ) {

      if ( !empty( $resizeWidth ) )
        $this->resizeWidth = $resizeWidth;

      if ( !empty( $resizeHeight ) )
        $this->resizeHeight = $resizeHeight;

      list( $width, $height ) = @getimagesize( $image );

      if ( $height < $width ) {

        // *** Image to be resized is wider (landscape)
        $imageWidth = $this->resizeWidth;
        $imageHeight= ( $height / $width ) * $this->resizeWidth;

      } else if ( $height > $width ) {

        // *** Image to be resized is taller (portrait)
        $imageWidth = ( $width / $height ) * $this->resizeHeight;
        $imageHeight= $this->resizeHeight;

      } else {
        // *** Image to be resized is a square

        if ( $this->resizeHeight < $this->resizeWidth ) {

          $imageWidth = $this->resizeWidth;
          $imageHeight= ( $height / $width ) * $this->resizeWidth;

        } else if ( $this->resizeHeight > $this->resizeWidth ) {

          $imageWidth = ( $width / $height ) * $this->resizeHeight;
          $imageHeight= $this->resizeHeight;

        } else {

          // *** Sqaure being resized to a square
          $imageWidth = $this->resizeWidth;
          $imageHeight= $this->resizeHeight;

        }
      }

      // if ( $this->resizeHeight <= 0 ) {
      //   $this->resizeHeight = ( $height / $width ) * $this->resizeWidth;
      // } else if ( $this->resizeWidth <= 0 ) {
      //   $this->resizeWidth = ( $width / $height ) * $this->resizeHeight;
      // }

      $this->originalImage = $this->create_image( $image );

      $this->resizedImage = @imagecreatetruecolor( $imageWidth, $imageHeight );

      $this->image_transparency();

      @imagecopyresampled( $this->resizedImage, $this->originalImage, 0, 0, 0, 0, $imageWidth, $imageHeight, $width, $height );

    }

    private function image_transparency() {
      // PNG/GIF Transparency
      @imagealphablending( $this->resizedImage, FALSE );
      @imagesavealpha( $this->resizedImage, TRUE );
      $black = @imagecolorallocate( $this->resizedImage, 0, 0, 0, 127 );
      @imagecolortransparent( $this->resizedImage, $black );
    }

    public function crop_image( $data ) {
      $newImageWidth = ceil( $data['width'] * $data['scale'] );
      $newImageHeight = ceil( $data['height'] * $data['scale'] );

      if ( isset( $data['image'] ) && $data['image'] != NULL ) {
        $this->originalImage = $this->create_image( $data['image'] );
      }

      $this->resizedImage = @imagecreatetruecolor( $newImageWidth, $newImageHeight );

      $this->image_transparency();

      @imagecopyresampled( $this->resizedImage, $this->originalImage, 0, 0, $data['cropx'], $data['cropy'], $newImageWidth, $newImageHeight, $data['width'], $data['height'] );

    }

    public function display_image( $imagePath, $width = '128px', $height = '128px', $quality = 100 ) {
      $this->resizeWidth = $width;
      $this->resizeHeight = $height;

      if ( !file_exists( $imagePath ) ) {
        $this->errors[] .= 'file_not_found';
        return FALSE;
      }

      // header( "Content-type: " . image_type_to_mime_type( exif_imagetype( $imagePath ) ) ); //Picture Format
      // header( "Expires: Mon, 01 Jul 2003 00:00:00 GMT" ); // Past date
      // header( "Last-Modified: " . gmdate("D, d M Y H:i:s" ) . " GMT"); // Consitnuously modified
      // header( "Cache-Control: no-cache, must-revalidate" ); // HTTP/1.1
      // header( "Pragma: no-cache" ); // NO CACHE

      if ( $this->resize_image( $imagePath ) ) {
        return $this->save_image( $imagePath, $quality );
      }

    }

    public function save_image( $filePath, $imageQuality = '100' ) {
      switch ( strtolower( $this->get_file_extension( $filePath )  ) ) {
        case 'jpg':
        case 'jpeg':
          $sourceImage = @imagejpeg( $this->resizedImage, $filePath, $imageQuality );
          break;
        case 'png':
          $scaleQuality = round( ( $imageQuality / 100 ) * 9 );
          $invertScaleQuality = 9 - $scaleQuality;
          $sourceImage = @imagepng( $this->resizedImage, $filePath, $invertScaleQuality );
          break;
        case 'gif':
          $sourceImage = @imagegif( $this->resizedImage, $filePath );
          break;
         case 'bmp':
          $sourceImage = @imagewbmp( $this->resizedImage, $filePath );
          break;
        default:
          $sourceImage = FALSE;
          break;
      }
      @imagedestroy( $this->resizedImage );
      @chmod( $filePath, 0777 );
      return $sourceImage;
    }

    public function delete_file( $file = NULL ) {
      return @unlink( $file );
    }

    /**
     * Deletes a directory and all files and folders under it
     * @return TRUE
     * @param $dir String Directory Path
     */

    public function delete_files( $dir ) {
      if ( is_dir( $dir ) ) {
        $dirLists = @scandir( $dir );
        foreach ( $dirLists as $list ) {
          if ( $list != '.' && $list != '..' ) {
            if ( @filetype( $dir . '/' . $list ) == 'dir' ) {
              $this->delete_files( $dir . '/' . $list );
            } else {
              $this->delete_file( $dir . '/' . $list );
            }
          }

        }
      }
      reset( $dirLists );
      if ( !rmdir( $dir ) ) {
        echo ('could not remove $dir');
        return FALSE;
      };
      @clearstatcache();
      return TRUE;
    }

    /**
     * Copies a directory and all files and folders under it
     * @return  TRUE
     * @param   $dir String Directory Path
     */

    public function copy_to_directory( $src, $dst, $permission = 0755 ) {
      if ( !file_exists( $src ) )
        return FALSE;

      $dir = @opendir( $src );
      if ( !file_exists( $dst ) ) {
        @mkdir( $dst, $permission );
      }

      while ( ( $file = @readdir( $dir ) ) !== FALSE ) {
        if ( ( $file != '.' ) && ( $file != '..' ) ) {
          if ( is_dir( $src . '/' . $file ) ) {
            $this->copy_file( $src . '/' . $file );
          } else {
            @copy( $src . '/' . $file, $dst . '/' . $file );
          }
        }
      }
      @closedir( $dir );
      @clearstatcache();
      return TRUE;
    }

    public function copy_file( $pattern, $dir, $permission = 0755 ) {
      if ( !file_exists( $dir ) ) {
        @mkdir( $dir, $permission );
      }

      foreach( glob( $pattern ) as $file ) {
        if ( !is_dir( $file ) && is_readable( $file ) ) {
          $dst = @realpath( $dir ) . '/' . basename( $file );
          @copy( $file, $dst );
        } else {
          $this->copy_file( $pattern, $file );
        }
      }
      @clearstatcache();
      return TRUE;
    }

    public function display_size( $value, $precision = 2 ) {
      if ( is_numeric( $value ) ) {
        $bytes = $value;
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

        $bytes = max( $bytes, 0 );
        $pow = floor( ( ( $bytes ) ? log( $bytes )  : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, $precision ) . $units[$pow];
      } else {
        $size = $this->file_size( $value );
        if ( !is_numeric( $size ) ) {
          $this->errors .= 'file_size_error';
          return FALSE;
        }
        $this->display_size( $size );
      }


    }

    public function errors(){
      foreach( $this->errors as $key => $value )
        return $value;
    }

    public function success() {
      return $this->confirmation;
    }


  }
?>