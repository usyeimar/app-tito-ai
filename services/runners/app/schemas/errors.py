"""
Error response schemas for the Tito AI API.

This module defines the standardized error response structure
used across the application, matching the custom exception handlers.
"""

from pydantic import BaseModel, ConfigDict, Field
from typing import List, Optional, Dict, Any


class ErrorLink(BaseModel):
    """Link object for HATEOAS navigation in errors."""

    href: str
    method: str


class ErrorSource(BaseModel):
    """Pointer to the source of the error (e.g., specific field)."""

    pointer: Optional[str] = None
    parameter: Optional[str] = None


class ErrorDetail(BaseModel):
    """Individual error detail."""

    code: str
    title: str
    source: Optional[ErrorSource] = None


class ErrorObject(BaseModel):
    """Main error object."""

    status: int
    code: str
    title: str
    docs_url: Optional[str] = Field(
        None, description="Link to documentation for this error."
    )
    details: Optional[List[ErrorDetail]] = Field(
        None, description="List of specific error details."
    )


class APIErrorResponse(BaseModel):
    """
    Standardized API Error Response.

    Replaces the default FastAPI/Pydantic validation error structure.
    """

    model_config = ConfigDict(populate_by_name=True)

    error: ErrorObject
    links: Optional[Dict[str, ErrorLink]] = Field(None, alias="_links")
    debug: Optional[Dict[str, Any]] = Field(None)
