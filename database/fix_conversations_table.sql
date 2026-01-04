-- Fix conversations table to remove or handle listing_id

USE doublelist_clone;

-- Option 1: Make listing_id nullable (if you want to keep it)
ALTER TABLE conversations 
MODIFY COLUMN listing_id INT NULL;

-- Option 2: Remove the foreign key and column (if not needed)
-- First, find the constraint name
SELECT CONSTRAINT_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'doublelist_clone' 
AND TABLE_NAME = 'conversations' 
AND COLUMN_NAME = 'listing_id';

-- Then drop the foreign key (replace 'conversations_ibfk_1' with actual name if different)
ALTER TABLE conversations DROP FOREIGN KEY conversations_ibfk_1;

-- Make the column nullable instead of removing it (useful for tracking what listing the conversation is about)
ALTER TABLE conversations 
MODIFY COLUMN listing_id INT NULL;

-- Add back the foreign key with NULL support
ALTER TABLE conversations 
ADD CONSTRAINT conversations_listing_fk 
FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL;