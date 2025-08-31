import React, { useState, useEffect } from 'react';
import { Upload, Image, Trash2, Search, Plus, FolderOpen, Download } from 'lucide-react';
import './App.css';

interface ImageItem {
  id: string;
  name: string;
  url: string;
  size: number;
  uploadDate: Date;
  category: string;
}

function App() {
  const [images, setImages] = useState<ImageItem[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('الكل');
  const [uploading, setUploading] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);

  const categories = ['الكل', 'شخصية', 'عمل', 'عائلة', 'سفر', 'أخرى'];

  useEffect(() => {
    // تحميل الصور المحفوظة من localStorage
    const savedImages = localStorage.getItem('qt-hub-images');
    if (savedImages) {
      setImages(JSON.parse(savedImages));
    }
  }, []);

  useEffect(() => {
    // حفظ الصور في localStorage
    localStorage.setItem('qt-hub-images', JSON.stringify(images));
  }, [images]);

  const handleFileUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = event.target.files;
    if (!files) return;

    setUploading(true);
    
    Array.from(files).forEach((file) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const newImage: ImageItem = {
          id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
          name: file.name,
          url: e.target?.result as string,
          size: file.size,
          uploadDate: new Date(),
          category: 'أخرى'
        };
        
        setImages(prev => [...prev, newImage]);
      };
      reader.readAsDataURL(file);
    });
    
    setUploading(false);
    setShowUploadModal(false);
  };

  const deleteImage = (id: string) => {
    setImages(prev => prev.filter(img => img.id !== id));
  };

  const updateImageCategory = (id: string, category: string) => {
    setImages(prev => prev.map(img => 
      img.id === id ? { ...img, category } : img
    ));
  };

  const filteredImages = images.filter(img => {
    const matchesSearch = img.name.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = selectedCategory === 'الكل' || img.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const downloadImage = (image: ImageItem) => {
    const link = document.createElement('a');
    link.href = image.url;
    link.download = image.name;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="app">
      <header className="header">
        <div className="container">
          <div className="header-content">
            <div className="logo">
              <Image size={32} color="#059669" />
              <h1>قت هب - معرض الصور</h1>
            </div>
            <button 
              className="btn btn-primary"
              onClick={() => setShowUploadModal(true)}
            >
              <Plus size={20} />
              رفع صورة جديدة
            </button>
          </div>
        </div>
      </header>

      <main className="main">
        <div className="container">
          <div className="controls">
            <div className="search-section">
              <div className="search-box">
                <Search size={20} color="#6b7280" />
                <input
                  type="text"
                  placeholder="البحث في الصور..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="input"
                />
              </div>
            </div>
            
            <div className="filters">
              <select 
                value={selectedCategory}
                onChange={(e) => setSelectedCategory(e.target.value)}
                className="input"
                style={{ width: 'auto', minWidth: '150px' }}
              >
                {categories.map(category => (
                  <option key={category} value={category}>{category}</option>
                ))}
              </select>
            </div>
          </div>

          <div className="stats">
            <div className="stat-card">
              <FolderOpen size={24} color="#059669" />
              <div>
                <h3>{filteredImages.length}</h3>
                <p>إجمالي الصور</p>
              </div>
            </div>
            <div className="stat-card">
              <Image size={24} color="#059669" />
              <div>
                <h3>{images.length}</h3>
                <p>جميع الصور</p>
              </div>
            </div>
          </div>

          {filteredImages.length === 0 ? (
            <div className="empty-state">
              <Image size={64} color="#9ca3af" />
              <h2>لا توجد صور</h2>
              <p>ابدأ برفع صورك الأولى</p>
              <button 
                className="btn btn-primary"
                onClick={() => setShowUploadModal(true)}
              >
                <Upload size={20} />
                رفع صورة
              </button>
            </div>
          ) : (
            <div className="image-grid">
              {filteredImages.map((image) => (
                <div key={image.id} className="image-card">
                  <div className="image-preview">
                    <img src={image.url} alt={image.name} />
                    <div className="image-overlay">
                      <button 
                        className="btn btn-secondary"
                        onClick={() => downloadImage(image)}
                      >
                        <Download size={16} />
                      </button>
                      <button 
                        className="btn btn-secondary"
                        onClick={() => deleteImage(image.id)}
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                  <div className="image-info">
                    <h3>{image.name}</h3>
                    <p className="image-meta">
                      {formatFileSize(image.size)} • {image.uploadDate.toLocaleDateString('ar-SA')}
                    </p>
                    <select
                      value={image.category}
                      onChange={(e) => updateImageCategory(image.id, e.target.value)}
                      className="input"
                      style={{ fontSize: '14px', padding: '8px' }}
                    >
                      {categories.filter(cat => cat !== 'الكل').map(category => (
                        <option key={category} value={category}>{category}</option>
                      ))}
                    </select>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>

      {showUploadModal && (
        <div className="modal-overlay" onClick={() => setShowUploadModal(false)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>رفع صور جديدة</h2>
              <button 
                className="close-btn"
                onClick={() => setShowUploadModal(false)}
              >
                ×
              </button>
            </div>
            <div className="modal-body">
              <div className="upload-area">
                <Upload size={48} color="#059669" />
                <p>اسحب الصور هنا أو اضغط للاختيار</p>
                <input
                  type="file"
                  multiple
                  accept="image/*"
                  onChange={handleFileUpload}
                  className="file-input"
                />
              </div>
              {uploading && (
                <div className="uploading">
                  <p>جاري رفع الصور...</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default App;
